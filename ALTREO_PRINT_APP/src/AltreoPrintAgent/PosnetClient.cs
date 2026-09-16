using System.Globalization;
using System.Net.Sockets;
using System.Text;

namespace AltreoPrintAgent;

public sealed class PosnetClient : IAsyncDisposable
{
    private const byte Stx = 0x02;
    private const byte Etx = 0x03;
    private const byte Tab = 0x09;
    private readonly TcpClient _client = new();
    private readonly Encoding _encoding;
    private NetworkStream? _stream;

    public PosnetClient()
    {
        Encoding.RegisterProvider(CodePagesEncodingProvider.Instance);
        _encoding = Encoding.GetEncoding(1250, EncoderFallback.ReplacementFallback, DecoderFallback.ReplacementFallback);
    }

    public async Task ConnectAsync(string host, int port, CancellationToken cancellationToken)
    {
        ValidateEndpoint(host, port);
        using var timeout = CancellationTokenSource.CreateLinkedTokenSource(cancellationToken);
        timeout.CancelAfter(TimeSpan.FromSeconds(5));
        try
        {
            await _client.ConnectAsync(host, port, timeout.Token);
            _stream = _client.GetStream();
        }
        catch (OperationCanceledException) when (!cancellationToken.IsCancellationRequested)
        {
            throw new TimeoutException($"Drukarka Posnet {host}:{port} nie odpowiedziała w ciągu 5 sekund.");
        }
        catch (SocketException exception)
        {
            throw new IOException($"Nie można połączyć się z drukarką Posnet {host}:{port}: {exception.Message}", exception);
        }
    }

    public async Task<string> TestNonFiscalAsync(CancellationToken cancellationToken)
    {
        await EnsureReadyAsync(cancellationToken);
        var started = false;
        try
        {
            await SendAsync("formstart", cancellationToken, "fn200", "fh75");
            started = true;
            await SendAsync("formline", cancellationToken, "fn200", "fl664", "s1       ALTREO PRINT AGENT\n");
            await SendAsync("formline", cancellationToken, "fn200", "fl664", "s1TEST POLACZENIA - WYDRUK NIEFISKALNY\n");
            await SendAsync("formline", cancellationToken, "fn200", "fl664", $"s1{DateTime.Now:yyyy-MM-dd HH:mm:ss}\n");
            await SendAsync("formend", cancellationToken, "fn200");
            started = false;
            return "Posnet potwierdził wykonanie wydruku niefiskalnego.";
        }
        finally
        {
            if (started)
            {
                try { await SendAsync("formend", CancellationToken.None, "fn200"); } catch { }
            }
        }
    }

    public async Task PrintNonFiscalReceiptAsync(FiscalJob job, CancellationToken cancellationToken)
    {
        await EnsureReadyAsync(cancellationToken);
        var started = false;
        try
        {
            await SendAsync("formstart", cancellationToken, "fn200", "fh75");
            started = true;
            await PrintFormLineAsync("ALTREO - PARAGON NIEFISKALNY", cancellationToken);
            await PrintFormLineAsync("NIE JEST DOWODEM SPRZEDAZY", cancellationToken);
            await PrintFormLineAsync("Zamowienie: " + Clean(job.Receipt.OrderNumber, 25), cancellationToken);
            await PrintFormLineAsync(DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss", CultureInfo.InvariantCulture), cancellationToken);
            await PrintFormLineAsync("--------------------------------", cancellationToken);
            foreach (var item in job.Receipt.Items)
            {
                await PrintFormLineAsync(Clean(item.Name, 36), cancellationToken);
                await PrintFormLineAsync($"{item.Quantity} x {Money(item.UnitCents)} = {Money(checked(item.UnitCents * item.Quantity))} PLN", cancellationToken);
            }
            await PrintFormLineAsync("--------------------------------", cancellationToken);
            await PrintFormLineAsync("RAZEM: " + Money(job.Receipt.TotalCents) + " PLN", cancellationToken);
            await PrintFormLineAsync("Wydruk testowy - bez fiskalizacji", cancellationToken);
            await SendAsync("formend", cancellationToken, "fn200");
            started = false;
        }
        finally
        {
            if (started)
            {
                try { await SendAsync("formend", CancellationToken.None, "fn200"); } catch { }
            }
        }
    }

    private Task<string> PrintFormLineAsync(string value, CancellationToken cancellationToken) =>
        SendAsync("formline", cancellationToken, "fn200", "fl664", "s1" + Clean(value, 39) + "\n");

    private static string Money(long cents) => (cents / 100m).ToString("0.00", CultureInfo.InvariantCulture);

    public async Task<string?> PrintFiscalReceiptAsync(FiscalJob job, CancellationToken cancellationToken)
    {
        await EnsureReadyAsync(cancellationToken, requireNoOpenTransaction: true);
        var vatRates = await ReadVatRatesAsync(cancellationToken);
        var transactionStarted = false;
        var finalizationAttempted = false;
        try
        {
            await SendAsync("ftrcfg", cancellationToken,
                "ccALTREO", "cnAGENT", "sn" + Clean(job.Receipt.OrderNumber, 30));
            await SendAsync("trinit", cancellationToken, "bm0");
            transactionStarted = true;

            foreach (var item in job.Receipt.Items)
            {
                var vat = NormalizeVat(item.Vat);
                if (!vatRates.TryGetValue(vat, out var vatIndex))
                    throw new InvalidOperationException($"Drukarka nie ma aktywnej stawki VAT {item.Vat}%.");
                await SendAsync("trline", cancellationToken,
                    "na" + Clean(item.Name, 40),
                    "vt" + vatIndex,
                    "pr" + item.UnitCents.ToString(CultureInfo.InvariantCulture),
                    "il" + item.Quantity.ToString(CultureInfo.InvariantCulture));
            }

            await SendAsync("trpayment", cancellationToken,
                "ty" + job.Receipt.PaymentType.ToString(CultureInfo.InvariantCulture),
                "wa" + job.Receipt.TotalCents.ToString(CultureInfo.InvariantCulture),
                "na" + Clean(job.Receipt.PaymentName, 20));
            finalizationAttempted = true;
            var response = await SendAsync("trend", cancellationToken,
                "to" + job.Receipt.TotalCents.ToString(CultureInfo.InvariantCulture),
                "fp" + job.Receipt.TotalCents.ToString(CultureInfo.InvariantCulture));
            transactionStarted = false;
            return FirstProperty(response, "nr") ?? FirstProperty(response, "nb");
        }
        catch
        {
            // After trend has been sent, the receipt may already be recorded even if its reply was lost.
            if (transactionStarted && !finalizationAttempted)
            {
                try { await SendAsync("prncancel", CancellationToken.None); } catch { }
            }
            throw;
        }
    }

    internal async Task<string> SendAsync(string command, CancellationToken cancellationToken, params string[] parameters)
    {
        if (_stream is null) throw new InvalidOperationException("Brak połączenia z drukarką Posnet.");
        var bodyText = command + "\t" + (parameters.Length == 0 ? "" : string.Join('\t', parameters) + "\t");
        var body = _encoding.GetBytes(bodyText);
        var crc = CalculateCrc(body).ToString("X4", CultureInfo.InvariantCulture);
        var frame = new byte[1 + body.Length + 1 + 4 + 1];
        frame[0] = Stx;
        body.CopyTo(frame, 1);
        frame[1 + body.Length] = (byte)'#';
        Encoding.ASCII.GetBytes(crc).CopyTo(frame, 2 + body.Length);
        frame[^1] = Etx;

        using var timeout = CancellationTokenSource.CreateLinkedTokenSource(cancellationToken);
        timeout.CancelAfter(TimeSpan.FromSeconds(12));
        try
        {
            await _stream.WriteAsync(frame, timeout.Token);
            await _stream.FlushAsync(timeout.Token);
            var response = await ReadFrameAsync(_stream, timeout.Token);
            var decoded = DecodeFrame(response);
            if (decoded.Contains('?'))
            {
                var error = decoded[(decoded.IndexOf('?') + 1)..].Trim();
                throw new PosnetException($"Drukarka odrzuciła polecenie {command} (błąd {error}).", error);
            }
            var responseCommand = decoded.Split('\t', 2)[0].Trim();
            if (!string.Equals(responseCommand, command, StringComparison.OrdinalIgnoreCase))
                throw new PosnetException($"Nieoczekiwana odpowiedź drukarki na {command}: {responseCommand}.");
            return decoded;
        }
        catch (OperationCanceledException) when (!cancellationToken.IsCancellationRequested)
        {
            throw new TimeoutException($"Drukarka nie odpowiedziała na polecenie {command}.");
        }
    }

    private async Task EnsureReadyAsync(CancellationToken cancellationToken, bool requireNoOpenTransaction = false)
    {
        var device = await SendAsync("sdev", cancellationToken);
        var deviceState = FirstProperty(device, "ds");
        if (deviceState is not null and not "0")
            throw new PosnetException("Drukarka nie jest gotowa (menu, komunikat lub oczekiwanie na klawisz).", deviceState);

        var printer = await SendAsync("sprn", cancellationToken);
        var printerState = FirstProperty(printer, "pr");
        if (printerState is not null and not "0")
            throw new PosnetException(PrinterStateMessage(printerState), printerState);

        if (requireNoOpenTransaction)
        {
            var communication = await SendAsync("scomm", cancellationToken);
            var transactionState = FirstProperty(communication, "ts");
            if (transactionState is not null and not "0")
                throw new PosnetException("Drukarka ma niezakończoną wcześniejszą transakcję. Wymaga sprawdzenia przed kolejnym paragonem.", transactionState);
        }
    }

    private async Task<Dictionary<string, int>> ReadVatRatesAsync(CancellationToken cancellationToken)
    {
        var response = await SendAsync("vatget", cancellationToken);
        var result = new Dictionary<string, int>(StringComparer.OrdinalIgnoreCase);
        foreach (var field in response.Split('\t', StringSplitOptions.RemoveEmptyEntries | StringSplitOptions.TrimEntries).Skip(1))
        {
            if (field.Length < 3 || field[0] != 'v') continue;
            var index = char.ToLowerInvariant(field[1]) - 'a';
            if (index is < 0 or > 6) continue;
            var value = field[2..].Replace(',', '.');
            result[NormalizeVat(value)] = index;
        }
        return result;
    }

    internal static ushort CalculateCrc(ReadOnlySpan<byte> data)
    {
        ushort crc = 0;
        foreach (var value in data)
        {
            crc ^= (ushort)(value << 8);
            for (var bit = 0; bit < 8; bit++)
                crc = (ushort)((crc & 0x8000) != 0 ? (crc << 1) ^ 0x1021 : crc << 1);
        }
        return crc;
    }

    internal static string? FirstProperty(string response, string key)
    {
        return response.Split('\t', StringSplitOptions.RemoveEmptyEntries | StringSplitOptions.TrimEntries)
            .Skip(1).FirstOrDefault(value => value.StartsWith(key, StringComparison.OrdinalIgnoreCase))?[key.Length..];
    }

    internal static void ValidateEndpoint(string host, int port)
    {
        if (string.IsNullOrWhiteSpace(host) || host.Length > 255 || host.ContainsAny('\r', '\n', '\t'))
            throw new InvalidOperationException("Adres drukarki fiskalnej jest nieprawidłowy.");
        if (port is < 1 or > 65535) throw new InvalidOperationException("Port drukarki fiskalnej musi mieścić się między 1 a 65535.");
    }

    private static async Task<byte[]> ReadFrameAsync(NetworkStream stream, CancellationToken cancellationToken)
    {
        var frame = new List<byte>(512);
        var buffer = new byte[1];
        while (frame.Count < 65536)
        {
            var read = await stream.ReadAsync(buffer, cancellationToken);
            if (read == 0) throw new EndOfStreamException("Drukarka zamknęła połączenie bez odpowiedzi.");
            if (frame.Count == 0 && buffer[0] != Stx) continue;
            frame.Add(buffer[0]);
            if (buffer[0] == Etx) return frame.ToArray();
        }
        throw new InvalidDataException("Odpowiedź drukarki przekroczyła dopuszczalny rozmiar.");
    }

    private string DecodeFrame(byte[] frame)
    {
        if (frame.Length < 7 || frame[0] != Stx || frame[^1] != Etx)
            throw new InvalidDataException("Drukarka zwróciła niepełną ramkę odpowiedzi.");
        var hashIndex = Array.LastIndexOf(frame, (byte)'#', frame.Length - 2);
        if (hashIndex < 2 || hashIndex + 5 != frame.Length - 1)
            throw new InvalidDataException("Odpowiedź drukarki nie zawiera poprawnej sumy kontrolnej.");
        var body = frame.AsSpan(1, hashIndex - 1).ToArray();
        var expected = Encoding.ASCII.GetString(frame, hashIndex + 1, 4);
        var actual = CalculateCrc(body).ToString("X4", CultureInfo.InvariantCulture);
        if (!string.Equals(expected, actual, StringComparison.OrdinalIgnoreCase))
            throw new InvalidDataException("Odpowiedź drukarki ma nieprawidłową sumę kontrolną.");
        return _encoding.GetString(body);
    }

    private static string NormalizeVat(string vat)
    {
        var value = vat.Trim().ToLowerInvariant().Replace(',', '.');
        if (value is "100" or "100.00" or "zw") return "zw";
        if (decimal.TryParse(value, NumberStyles.Number, CultureInfo.InvariantCulture, out var number))
            return number.ToString("0.##", CultureInfo.InvariantCulture);
        return value;
    }

    private static string Clean(string? value, int maxLength)
    {
        var clean = new string((value ?? "").Select(character => char.IsControl(character) || character is '#' ? ' ' : character).ToArray()).Trim();
        return clean.Length <= maxLength ? clean : clean[..maxLength];
    }

    private static string PrinterStateMessage(string state) => state switch
    {
        "1" => "Drukarka ma podniesioną dźwignię mechanizmu.",
        "3" => "Drukarka ma otwartą pokrywę.",
        "4" or "5" => "W drukarce brakuje papieru.",
        "6" => "Drukarka zgłasza nieprawidłową temperaturę lub zasilanie.",
        "8" => "Drukarka zgłasza błąd obcinacza.",
        _ => $"Drukarka nie jest gotowa (stan mechanizmu {state})."
    };

    public async ValueTask DisposeAsync()
    {
        if (_stream is not null) await _stream.DisposeAsync();
        _client.Dispose();
    }
}

public sealed class PosnetException : IOException
{
    public string? DeviceErrorCode { get; }
    public PosnetException(string message, string? deviceErrorCode = null) : base(message) => DeviceErrorCode = deviceErrorCode;
}
