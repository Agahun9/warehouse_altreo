using System.Globalization;
using System.Net.Sockets;
using System.Text;
using System.Text.RegularExpressions;

namespace AltreoPrintAgent;

/// <summary>
/// Drukarki online Novitus (Deon Online, HD Online, Bono Online) — „Opis protokołu komunikacyjnego XML” 1.08 PL,
/// polskie znaczniki, TCP/IP, kodowanie Windows-1250. Rozkazy wykonawcze (paragon, pozycja, płatność, wydruk)
/// nie mają odpowiedzi, dlatego wynik zawsze potwierdzamy zapytaniami enq_pl / informacja / blad.
/// </summary>
public sealed partial class NovitusClient : IFiscalPrinterClient
{
    public const int DefaultPort = 6001;
    /// <summary>Bufor komunikacji drukarki ma 5000 bajtów; zostawiamy zapas na znaczniki pakietu.</summary>
    internal const int MaxPacketBytes = 4500;
    internal const int NonFiscalLineWidth = 32;
    private const string Cashier = "ALTREO";
    private static readonly TimeSpan QueryTimeout = TimeSpan.FromSeconds(12);
    private static readonly TimeSpan PrintTimeout = TimeSpan.FromSeconds(45);
    private readonly TcpClient _client = new();
    private readonly Encoding _encoding;
    private readonly StringBuilder _received = new();
    private NetworkStream? _stream;

    public NovitusClient()
    {
        Encoding.RegisterProvider(CodePagesEncodingProvider.Instance);
        _encoding = Encoding.GetEncoding(1250, EncoderFallback.ReplacementFallback, DecoderFallback.ReplacementFallback);
    }

    public string DeviceLabel => "Novitus";

    public async Task ConnectAsync(string host, int port, CancellationToken cancellationToken)
    {
        PosnetClient.ValidateEndpoint(host, port);
        using var timeout = CancellationTokenSource.CreateLinkedTokenSource(cancellationToken);
        timeout.CancelAfter(TimeSpan.FromSeconds(5));
        try
        {
            await _client.ConnectAsync(host, port, timeout.Token);
            _stream = _client.GetStream();
        }
        catch (OperationCanceledException) when (!cancellationToken.IsCancellationRequested)
        {
            throw new TimeoutException($"Drukarka Novitus {host}:{port} nie odpowiedziała w ciągu 5 sekund.");
        }
        catch (SocketException exception)
        {
            throw new IOException($"Nie można połączyć się z drukarką Novitus {host}:{port}: {exception.Message}", exception);
        }
    }

    public async Task<string> TestNonFiscalAsync(CancellationToken cancellationToken)
    {
        await EnsureReadyAsync(cancellationToken);
        foreach (var packet in NonFiscalPackets([
            "ALTREO PRINT AGENT",
            "TEST POLACZENIA - WYDRUK NIEFISKALNY",
            DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss", CultureInfo.InvariantCulture)]))
            await SendAsync(packet, cancellationToken);
        await EnsureLastCommandOkAsync("wydruku niefiskalnego", cancellationToken);
        return "Novitus potwierdził wykonanie wydruku niefiskalnego.";
    }

    public async Task PrintNonFiscalReceiptAsync(FiscalJob job, CancellationToken cancellationToken)
    {
        await EnsureReadyAsync(cancellationToken);
        var lines = new List<string> { "ALTREO - PARAGON NIEFISKALNY", "NIE JEST DOWODEM SPRZEDAZY", job.Receipt.OrderNumber, "zam:#" + job.Receipt.OrderId,
            DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss", CultureInfo.InvariantCulture), new string('-', NonFiscalLineWidth) };
        foreach (var item in job.Receipt.Items)
        {
            lines.Add(item.Name);
            lines.Add($"{item.Quantity} x {Money(item.UnitCents)} = {Money(checked(item.UnitCents * item.Quantity))} VAT {item.Vat}{(item.Vat == "zw" ? "" : "%")}");
        }
        lines.Add(new string('-', NonFiscalLineWidth));
        foreach (var group in job.Receipt.Items.GroupBy(item => NormalizeVat(item.Vat)))
        {
            var rate = group.Key == "zw" ? 0 : int.Parse(group.Key, CultureInfo.InvariantCulture);
            var tax = group.Sum(item =>
            {
                var itemGross = checked(item.UnitCents * item.Quantity);
                var itemNet = decimal.ToInt64(decimal.Round(itemGross * 100m / (100 + rate), 0, MidpointRounding.AwayFromZero));
                return itemGross - itemNet;
            });
            lines.Add($"VAT {group.Key}{(group.Key == "zw" ? "" : "%")}: {Money(tax)} PLN");
        }
        lines.Add("RAZEM: " + Money(job.Receipt.TotalCents) + " PLN");
        lines.Add("Platnosc: " + job.Receipt.PaymentName);
        lines.Add("Wydruk testowy - bez fiskalizacji");
        foreach (var packet in NonFiscalPackets(lines))
            await SendAsync(packet, cancellationToken);
        await EnsureLastCommandOkAsync("paragonu niefiskalnego", cancellationToken);
    }

    public async Task<string?> PrintFiscalReceiptAsync(FiscalJob job, CancellationToken cancellationToken)
    {
        await EnsureReadyAsync(cancellationToken);
        var device = await QueryAsync(Packet(DeviceInfoRequest), "informacja", cancellationToken);
        if (device["otwarta_transakcja"] == "tak")
            throw new NovitusException("Drukarka Novitus ma niezakończoną wcześniejszą transakcję. Wymaga sprawdzenia przed kolejnym paragonem.");
        if (device["zafiskalizowana"] == "nie")
            throw new NovitusException("Drukarka Novitus nie jest zafiskalizowana (tryb szkoleniowy). Ustaw w SalesCenter tryb SANDBOX albo zafiskalizuj urządzenie.");
        var vatRates = ParseVatRates(device.Xml, job.Receipt.VatRates);
        var reference = ReceiptReference(job);
        if (reference.Length > 30)
            throw new InvalidOperationException("Numer dokumentu i zamówienia przekracza 30 znaków numeru systemowego paragonu.");
        foreach (var item in job.Receipt.Items)
            if (!vatRates.ContainsKey(NormalizeVat(item.Vat)))
                throw new InvalidOperationException($"Drukarka nie ma aktywnej stawki VAT {item.Vat}.");
        var buyerNip = new string((job.Receipt.BuyerNip ?? "").Where(char.IsDigit).ToArray());
        if (buyerNip.Length > 0)
        {
            if (buyerNip.Length != 10)
                throw new InvalidOperationException("NIP nabywcy musi mieć 10 cyfr.");
            if (job.Receipt.TotalCents > 45000)
                throw new InvalidOperationException("Paragon z NIP nabywcy nie może przekraczać 450 zł brutto.");
        }

        var previous = await QueryAsync(Packet(Element("informacja", ("akcja", "ostatnia_transakcja"))), "informacja", cancellationToken);
        var packets = BuildReceiptPackets(job, vatRates, reference, buyerNip);
        var opened = false;
        var closeSent = false;
        try
        {
            foreach (var packet in packets)
            {
                await SendAsync(packet, cancellationToken);
                opened = true;
                closeSent = packet.Contains("akcja=\"zamknij\"", StringComparison.Ordinal);
            }

            var state = await QueryAsync(Packet("<enq_pl/>"), "enq_pl", cancellationToken, PrintTimeout);
            if (state["tryb_transakcji"] == "tak")
            {
                // Paragon nadal otwarty = drukarka nie przyjęła zamknięcia; otwarty paragon nie jest zarejestrowaną sprzedażą.
                var code = await LastErrorAsync();
                await CancelReceiptAsync();
                throw new NovitusException($"Drukarka Novitus nie zamknęła paragonu{ErrorSuffix(code)}. Paragon anulowano — sprzedaż nie została zarejestrowana.", code);
            }
            var last = await QueryAsync(Packet(Element("informacja", ("akcja", "ostatnia_transakcja"))), "informacja", cancellationToken);
            var number = last["numer"];
            var unchanged = !string.IsNullOrEmpty(number) && number == previous["numer"] && last["data"] == previous["data"];
            if (state["ostatnia_transakcja_ok"] == "nie" || last["stan"] is "anulowany" or "otwarty" || unchanged)
            {
                var code = await LastErrorAsync();
                throw new NovitusException($"Drukarka Novitus nie potwierdziła zakończenia paragonu{ErrorSuffix(code)}. Sprawdź wydruk i raport urządzenia przed ponowieniem.", code);
            }
            return string.IsNullOrWhiteSpace(number) ? null : number;
        }
        catch (Exception exception) when (exception is not NovitusException && opened && !closeSent)
        {
            // Zamknięcie nie zostało wysłane, więc paragon nie mógł zostać zarejestrowany — bezpiecznie go anulujemy.
            await CancelReceiptAsync();
            throw;
        }
    }

    internal static IReadOnlyList<string> BuildReceiptPackets(FiscalJob job, IReadOnlyDictionary<string, string> vatRates, string reference, string buyerNip)
    {
        var commands = new List<string> { Element("paragon", ("akcja", "poczatek"), ("tryb", "online")) };
        foreach (var item in job.Receipt.Items)
        {
            var letter = vatRates[NormalizeVat(item.Vat)];
            commands.Add(Element("pozycja",
                ("nazwa", Clean(item.Name, 40)),
                ("ilosc", item.Quantity.ToString(CultureInfo.InvariantCulture)),
                ("jednostka", "szt"),
                ("stawka", letter),
                ("cena", Money(item.UnitCents)),
                ("kwota", Money(checked(item.UnitCents * item.Quantity))),
                ("plu", ""),
                ("opis", ""),
                ("akcja", "sprzedaz")));
        }
        if (job.Receipt.TotalCents > 0)
            commands.Add(Element("platnosc",
                ("typ", PaymentType(job.Receipt.PaymentType)),
                ("akcja", "dodaj"),
                ("wartosc", Money(job.Receipt.TotalCents)),
                ("nazwa", Clean(job.Receipt.PaymentName, 20))));
        var close = new List<(string, string)>
        {
            ("akcja", "zamknij"),
            // Wiodący @ lub # zamienia numer systemowy w kod QR / kreskowy, więc go usuwamy.
            ("numer_systemowy", Clean(reference, 30).TrimStart('@', '#')),
            ("kasjer", Cashier),
            ("kwota", Money(job.Receipt.TotalCents))
        };
        if (buyerNip.Length > 0) close.Add(("nip", buyerNip));
        commands.Add(Element("paragon", close.ToArray()));
        return Chunk(commands);
    }

    internal static IReadOnlyList<string> NonFiscalPackets(IEnumerable<string> lines)
    {
        var wrapped = lines.SelectMany(line => Wrap(line, NonFiscalLineWidth)).Select(line => "<linia>" + line + "</linia>").ToList();
        var packets = new List<string>();
        var current = new List<string>();
        foreach (var line in wrapped)
        {
            if (current.Count > 0 && Encoding.Latin1.GetByteCount(NonFiscal(current.Append(line))) > MaxPacketBytes)
            {
                packets.Add(NonFiscal(current));
                current.Clear();
            }
            current.Add(line);
        }
        if (current.Count > 0) packets.Add(NonFiscal(current));
        return packets;

        static string NonFiscal(IEnumerable<string> body) =>
            Packet("<wydruk_niefiskalny naglowek_wydruku_niefiskalnego=\"tak\">" + string.Concat(body) + "</wydruk_niefiskalny>");
    }

    private static IReadOnlyList<string> Chunk(IReadOnlyList<string> commands)
    {
        var packets = new List<string>();
        var current = new StringBuilder();
        foreach (var command in commands)
        {
            if (current.Length > 0 && current.Length + command.Length + 17 > MaxPacketBytes)
            {
                packets.Add(Packet(current.ToString()));
                current.Clear();
            }
            current.Append(command);
        }
        if (current.Length > 0) packets.Add(Packet(current.ToString()));
        return packets;
    }

    private static string DeviceInfoRequest => Element("informacja",
        ("akcja", "urzadzenie"), ("typ", "paragon"), ("ostatni_blad", "?"), ("zafiskalizowana", "?"), ("otwarta_transakcja", "?"),
        ("ostatnia_transakcja_bledna", "?"), ("ilosc_zerowan_pamieci", "?"), ("data", "?"), ("ilosc_paragonow", "?"), ("gotowka", "?"),
        ("numer_unikatowy", "?"), ("numer_ostatniego_paragonu", "?"), ("numer_ostatniej_faktury", "?"), ("numer_ostatniego_wydruku", "?"));

    private async Task EnsureReadyAsync(CancellationToken cancellationToken)
    {
        // Tryb cichy: błąd nie blokuje drukarki komunikatem czekającym na klawisz; odczytujemy go rozkazem blad.
        await SendAsync(Packet(Element("blad", ("akcja", "ustaw"), ("wartosc", "cichy"))), cancellationToken);
        var ready = await QueryAsync(Packet("<dle_pl/>"), "dle_pl", cancellationToken);
        if (ready["brak_papieru"] == "tak")
            throw new NovitusException("W drukarce Novitus brakuje papieru.");
        if (ready["blad_urzadzenia"] == "tak")
            throw new NovitusException("Drukarka Novitus zgłasza błąd mechanizmu drukującego (pokrywa, papier lub obcinacz).");
        if (ready["online"] != "tak")
            throw new NovitusException("Drukarka Novitus nie jest gotowa (menu, komunikat lub raport w toku).");
    }

    private async Task EnsureLastCommandOkAsync(string what, CancellationToken cancellationToken)
    {
        var state = await QueryAsync(Packet("<enq_pl/>"), "enq_pl", cancellationToken, PrintTimeout);
        if (state["ostatni_rozkaz_ok"] != "nie") return;
        var code = await LastErrorAsync();
        throw new NovitusException($"Drukarka Novitus odrzuciła polecenie {what}{ErrorSuffix(code)}.", code);
    }

    private async Task<string?> LastErrorAsync()
    {
        try
        {
            var error = await QueryAsync(Packet(Element("blad", ("akcja", "odczytaj"), ("wartosc", ""))), "blad", CancellationToken.None);
            var value = error["wartosc"];
            if (string.IsNullOrWhiteSpace(value)) value = DigitsPattern().Match(error.Xml).Value;
            return string.IsNullOrWhiteSpace(value) || value == "0" ? null : value.Trim();
        }
        catch
        {
            return null;
        }
    }

    private async Task CancelReceiptAsync()
    {
        try { await SendAsync(Packet(Element("paragon", ("akcja", "anuluj"))), CancellationToken.None); } catch { }
    }

    internal async Task SendAsync(string packet, CancellationToken cancellationToken)
    {
        if (_stream is null) throw new InvalidOperationException("Brak połączenia z drukarką Novitus.");
        var bytes = _encoding.GetBytes(packet);
        if (bytes.Length > 5000) throw new InvalidOperationException("Pakiet dla drukarki Novitus przekracza 5000 bajtów.");
        using var timeout = CancellationTokenSource.CreateLinkedTokenSource(cancellationToken);
        timeout.CancelAfter(QueryTimeout);
        try
        {
            await _stream.WriteAsync(bytes, timeout.Token);
            await _stream.FlushAsync(timeout.Token);
        }
        catch (OperationCanceledException) when (!cancellationToken.IsCancellationRequested)
        {
            throw new TimeoutException("Drukarka Novitus nie przyjęła danych w wymaganym czasie.");
        }
    }

    internal async Task<NovitusElement> QueryAsync(string packet, string element, CancellationToken cancellationToken, TimeSpan? wait = null)
    {
        if (_stream is null) throw new InvalidOperationException("Brak połączenia z drukarką Novitus.");
        // Odrzucamy resztki wcześniejszych odpowiedzi, żeby nie pomylić ich z odpowiedzią na to zapytanie.
        _received.Clear();
        while (_stream.DataAvailable) _ = await _stream.ReadAsync(new byte[4096], cancellationToken);
        await SendAsync(packet, cancellationToken);
        using var timeout = CancellationTokenSource.CreateLinkedTokenSource(cancellationToken);
        timeout.CancelAfter(wait ?? QueryTimeout);
        try
        {
            for (var attempt = 0; attempt < 5; attempt++)
            {
                var reply = await ReadPacketAsync(timeout.Token);
                var found = FindElement(reply, element);
                if (found is not null) return found;
            }
            throw new InvalidDataException($"Drukarka Novitus nie zwróciła odpowiedzi <{element}>.");
        }
        catch (OperationCanceledException) when (!cancellationToken.IsCancellationRequested)
        {
            throw new TimeoutException($"Drukarka Novitus nie odpowiedziała na zapytanie <{element}>.");
        }
    }

    private async Task<string> ReadPacketAsync(CancellationToken cancellationToken)
    {
        const string end = "</pakiet>";
        var buffer = new byte[2048];
        while (true)
        {
            var text = _received.ToString();
            var endIndex = text.IndexOf(end, StringComparison.OrdinalIgnoreCase);
            if (endIndex >= 0)
            {
                var packetEnd = endIndex + end.Length;
                _received.Remove(0, packetEnd);
                return text[..packetEnd];
            }
            if (_received.Length > 65536) throw new InvalidDataException("Odpowiedź drukarki Novitus przekroczyła dopuszczalny rozmiar.");
            var read = await _stream!.ReadAsync(buffer, cancellationToken);
            if (read == 0) throw new EndOfStreamException("Drukarka Novitus zamknęła połączenie bez odpowiedzi.");
            _received.Append(_encoding.GetString(buffer, 0, read));
        }
    }

    internal static string Packet(string content) => "<pakiet>" + content + "</pakiet>";

    internal static string Element(string name, params (string Name, string Value)[] attributes)
    {
        var builder = new StringBuilder("<").Append(name);
        foreach (var (attribute, value) in attributes)
            builder.Append(' ').Append(attribute).Append("=\"").Append(value).Append('"');
        return builder.Append("></").Append(name).Append('>').ToString();
    }

    internal static NovitusElement? FindElement(string packet, string name)
    {
        var match = Regex.Match(packet, "<" + Regex.Escape(name) + @"(?=[\s/>])([^>]*)>", RegexOptions.IgnoreCase);
        if (!match.Success) return null;
        var attributes = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
        foreach (Match attribute in AttributePattern().Matches(match.Groups[1].Value))
            attributes[attribute.Groups[1].Value] = attribute.Groups[2].Value.Trim();
        return new NovitusElement(name, attributes, packet[match.Index..]);
    }

    /// <summary>Zwraca mapę stawka → litera PTU. Brak litery w odpowiedzi oznacza stawkę nieaktywną.</summary>
    internal static Dictionary<string, string> ParseVatRates(string response, IReadOnlyDictionary<string, string>? expected = null)
    {
        var letters = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
        foreach (Match match in VatPattern().Matches(response))
            letters[match.Groups[1].Value.ToUpperInvariant()] = NormalizeVat(match.Groups[2].Value);
        if (letters.Count == 0)
            throw new InvalidOperationException("Drukarka Novitus nie zwróciła stawek PTU.");
        if (expected is not null)
        {
            if (expected.Count != 7)
                throw new InvalidOperationException("Konfiguracja VAT musi zawierać wszystkie stawki A–G.");
            foreach (var letter in "ABCDEFG")
            {
                var key = letter.ToString();
                var actual = letters.TryGetValue(key, out var value) ? value : "nieaktywna";
                if (!expected.TryGetValue(key, out var rate) || NormalizeVat(rate) != actual)
                    throw new InvalidOperationException($"Stawka VAT {key} w drukarce różni się od ustawień SalesCenter. Sprawdź stawki A–G.");
            }
        }
        var result = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
        foreach (var letter in "ABCDEFG")
            if (letters.TryGetValue(letter.ToString(), out var rate) && rate != "nieaktywna" && !result.ContainsKey(rate))
                result[rate] = letter.ToString();
        return result;
    }

    internal static string NormalizeVat(string vat)
    {
        var value = vat.Trim().TrimEnd('%').Trim().ToLowerInvariant();
        return value is "wolny" or "zw" ? "zw" : PosnetClient.NormalizeVat(value);
    }

    internal static string PaymentType(int type) => type switch
    {
        0 => "gotowka",
        2 => "karta",
        3 => "czek",
        4 => "voucher", // Novitus drukuje typ „voucher” jako „Bon”.
        5 => "kredyt",
        7 => "bon",     // …a typ „bon” jako „Voucher”.
        8 => "przelew",
        _ => "inna"
    };

    /// <summary>Wartości atrybutów nie mogą zawierać znaków 0x22 i 0x7F; znaczniki XML również usuwamy.</summary>
    internal static string Clean(string? value, int maxLength)
    {
        var clean = new string((value ?? "").Select(character => character switch
        {
            '"' => '\'',
            '<' or '>' => ' ',
            '&' => '+',
            _ when char.IsControl(character) => ' ',
            _ => character
        }).ToArray()).Trim();
        return clean.Length <= maxLength ? clean : clean[..maxLength].TrimEnd();
    }

    private static IEnumerable<string> Wrap(string value, int width)
    {
        var text = Clean(value, 400);
        if (text.Length == 0) { yield return ""; yield break; }
        while (text.Length > width)
        {
            var cut = text.LastIndexOf(' ', width);
            if (cut <= 0) cut = width;
            yield return text[..cut].TrimEnd();
            text = text[cut..].TrimStart();
        }
        if (text.Length > 0) yield return text;
    }

    private static string ReceiptReference(FiscalJob job) => job.Receipt.OrderNumber + " zam:#" + job.Receipt.OrderId;
    private static string Money(long cents) => (cents / 100m).ToString("0.00", CultureInfo.InvariantCulture);

    private static string ErrorSuffix(string? code) => code is null ? "" : $" (błąd {code}: {DescribeError(code)})";

    internal static string DescribeError(string code) => code switch
    {
        "3" => "nieprawidłowa ilość parametrów",
        "4" => "nieprawidłowy parametr",
        "16" => "nieprawidłowa nazwa towaru",
        "17" => "nieprawidłowa ilość",
        "18" => "nieprawidłowa stawka PTU towaru",
        "19" => "nieprawidłowa cena towaru",
        "20" => "nieprawidłowa wartość towaru",
        "21" => "paragon nie został rozpoczęty",
        "25" => "nieprawidłowy tekst lub nazwa kasjera",
        "26" or "30" => "nieprawidłowa wartość płatności",
        "27" => "nieprawidłowa wartość całkowita",
        "34" => "nieprawidłowa wartość lub tekst",
        "51" => "nieprawidłowa kwota",
        "82" or "99" => "niedozwolony rozkaz",
        "1002" => "paragon jest już rozpoczęty",
        "1003" => "brak identyfikatora stawki PTU",
        "1006" => "drukarka nie jest w trybie fiskalnym",
        "1007" => "nie zaprogramowano stawek PTU",
        "1008" => "pamięć fiskalna pełna",
        "1026" => "przepełnienie bufora transmisji",
        "1031" => "rozkaz wysłany w niewłaściwym trybie",
        "1034" => "drukarka fiskalna jest zajęta",
        "1037" => "brak papieru",
        "1092" => "brak pozycji na paragonie",
        "1098" => "brak lub błędny NIP nabywcy",
        "1100" => "blokada sprzedaży",
        "1116" => "wykonaj zaległy raport dobowy",
        "9999" => "błąd krytyczny",
        _ => "zobacz kody błędów w dokumentacji Novitus"
    };

    [GeneratedRegex(@"([A-Za-z_][\w-]*)\s*=\s*[""“”]([^""“”]*)[""“”]")]
    private static partial Regex AttributePattern();

    [GeneratedRegex(@"<stawka\s+nazwa\s*=\s*[""“”]([A-Ga-g])[""“”]\s*>([^<]*)</stawka>", RegexOptions.IgnoreCase)]
    private static partial Regex VatPattern();

    [GeneratedRegex(@"\d+")]
    private static partial Regex DigitsPattern();

    public async ValueTask DisposeAsync()
    {
        if (_stream is not null) await _stream.DisposeAsync();
        _client.Dispose();
    }
}

public sealed record NovitusElement(string Name, IReadOnlyDictionary<string, string> Attributes, string Xml)
{
    public string? this[string attribute] => Attributes.TryGetValue(attribute, out var value) ? value : null;
}

public sealed class NovitusException : IOException
{
    public string? DeviceErrorCode { get; }
    public NovitusException(string message, string? deviceErrorCode = null) : base(message) => DeviceErrorCode = deviceErrorCode;
}
