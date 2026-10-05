using System.Net;
using System.Net.Sockets;
using System.Text;
using Xunit;

namespace AltreoPrintAgent.Tests;

public sealed class NovitusTests
{
    private const string DeviceInfo = "<pakiet><informacja akcja=\"urzadzenie\" typ=\"paragon\" ostatni_blad=\"0\" zafiskalizowana=\"tak\" otwarta_transakcja=\"nie\" numer_ostatniego_paragonu=\"41\">"
        + "<stawka nazwa=\"A\">23.00</stawka><stawka nazwa=\"B\">8.00</stawka><stawka nazwa=\"C\">5.00</stawka><stawka nazwa=\"D\">0.00</stawka><stawka nazwa=\"E\">wolny</stawka>"
        + "</informacja></pakiet>";
    private static readonly Dictionary<string,string> Rates = new() { ["A"]="23",["B"]="8",["C"]="5",["D"]="0",["E"]="zw",["F"]="nieaktywna",["G"]="nieaktywna" };

    [Fact]
    public void VatRatesMapToLettersAndMissingLettersAreInactive()
    {
        var rates = NovitusClient.ParseVatRates(DeviceInfo, Rates);
        Assert.Equal("A", rates["23"]); Assert.Equal("B", rates["8"]); Assert.Equal("C", rates["5"]);
        Assert.Equal("D", rates["0"]); Assert.Equal("E", rates["zw"]); Assert.False(rates.ContainsKey("7"));
    }

    [Fact]
    public void VatMismatchUsesTheRetryableMessage()
    {
        var exception = Assert.Throws<InvalidOperationException>(() => NovitusClient.ParseVatRates(DeviceInfo.Replace(">8.00<", ">7.00<"), Rates));
        Assert.Equal("Stawka VAT B w drukarce różni się od ustawień SalesCenter. Sprawdź stawki A–G.", exception.Message);
    }

    [Fact]
    public void AttributeValuesNeverContainForbiddenCharacters()
    {
        Assert.Equal("Kabel 'HDMI' + adapter  2m", NovitusClient.Clean("Kabel \"HDMI\" & adapter <2m>", 40));
        Assert.Equal(40, NovitusClient.Clean(new string('x', 90), 40).Length);
    }

    [Fact]
    public void LongReceiptsAreSplitBelowDeviceBufferWithCloseLast()
    {
        var items = Enumerable.Range(1, 60).Select(index => new FiscalReceiptItem($"Komputer gamingowy pozycja numer {index}", 1, 100, "23")).ToArray();
        var job = Job(new FiscalReceiptPayload(1, "PAR/1", "PLN", 6000, items, 8, "Przelew", null, Rates));
        var packets = NovitusClient.BuildReceiptPackets(job, new Dictionary<string,string> { ["23"]="A" }, "PAR/1 zam:#1", "");
        Assert.True(packets.Count > 1);
        Assert.All(packets, packet => Assert.True(Encoding.Latin1.GetByteCount(packet) < 5000));
        Assert.StartsWith("<pakiet><paragon akcja=\"poczatek\"", packets[0]);
        Assert.Single(packets, packet => packet.Contains("akcja=\"zamknij\""));
        Assert.Contains("akcja=\"zamknij\"", packets[^1]);
    }

    [Fact]
    public void DiscoveryRecognisesNovitusAsSeparateDevice()
    {
        var devices = PrinterDiscovery.ParseFiscalPrinterLines("Novitus Deon Online\t192.168.1.16\t6001\nPosnet Trio\t192.168.1.15\t6666");
        Assert.Contains(devices, device => device.Protocol == FiscalProtocols.Novitus && device.DeviceKey == "novitus:192.168.1.16:6001");
        Assert.Contains(devices, device => device.Protocol == FiscalProtocols.Posnet && device.DeviceKey == "tcp:192.168.1.15:6666");
    }

    [Fact]
    public void OlderServerJobsWithoutProtocolStayPosnet()
    {
        Assert.Equal(FiscalProtocols.Posnet, FiscalProtocols.Normalize(null));
        Assert.IsType<PosnetClient>(FiscalReceiptService.CreateClient(null));
        Assert.IsType<NovitusClient>(FiscalReceiptService.CreateClient("NOVITUS"));
    }

    [Fact]
    public async Task FiscalReceiptCarriesLettersPaymentNipAndReturnsDeviceNumber()
    {
        var run = await RunAsync(Scenario.Success);
        Assert.Equal(JobStatuses.Printed, run.Result.Status);
        Assert.Equal("42", run.Result.Reference);
        var all = string.Concat(run.Packets);
        Assert.Contains("<blad akcja=\"ustaw\" wartosc=\"cichy\">", all);
        Assert.Contains("<paragon akcja=\"poczatek\" tryb=\"online\">", all);
        Assert.Contains("nazwa=\"Towar 23\" ilosc=\"1\" jednostka=\"szt\" stawka=\"A\" cena=\"1.00\" kwota=\"1.00\"", all);
        Assert.Contains("nazwa=\"Towar zw\" ilosc=\"1\" jednostka=\"szt\" stawka=\"E\"", all);
        Assert.Contains("<platnosc typ=\"przelew\" akcja=\"dodaj\" wartosc=\"5.00\" nazwa=\"Przelew\">", all);
        Assert.Contains("<paragon akcja=\"zamknij\" numer_systemowy=\"PAR/1 zam:#1\" kasjer=\"ALTREO\" kwota=\"5.00\" nip=\"5260250274\">", all);
        Assert.DoesNotContain("akcja=\"anuluj\"", all);
    }

    [Fact]
    public async Task RejectedCloseIsCancelledAndReportedWithDeviceError()
    {
        var run = await RunAsync(Scenario.RejectClose);
        Assert.Equal(JobStatuses.Error, run.Result.Status);
        Assert.Contains("błąd 27", run.Result.Message);
        Assert.Contains("nie została zarejestrowana", run.Result.Message);
        Assert.Contains(run.Packets, packet => packet.Contains("<paragon akcja=\"anuluj\">"));
    }

    [Fact]
    public async Task VatMismatchDoesNotOpenReceipt()
    {
        var run = await RunAsync(Scenario.Success, DeviceInfo.Replace(">8.00<", ">7.00<"));
        Assert.Equal(JobStatuses.Error, run.Result.Status);
        Assert.DoesNotContain(run.Packets, packet => packet.Contains("<paragon"));
    }

    [Fact]
    public async Task LostConnectionAfterCloseNeverCancelsOrRepeats()
    {
        var run = await RunAsync(Scenario.DropAfterClose);
        Assert.Equal(JobStatuses.Error, run.Result.Status);
        Assert.Single(run.Packets, packet => packet.Contains("akcja=\"zamknij\""));
        Assert.DoesNotContain(run.Packets, packet => packet.Contains("akcja=\"anuluj\""));
    }

    [Fact]
    public async Task SandboxPrintsOnlyNonFiscalDocument()
    {
        var run = await RunAsync(Scenario.Success, environment: "sandbox");
        Assert.Equal(JobStatuses.Printed, run.Result.Status);
        Assert.Contains(run.Packets, packet => packet.Contains("<wydruk_niefiskalny"));
        Assert.DoesNotContain(run.Packets, packet => packet.Contains("<paragon"));
        Assert.All(run.Packets.SelectMany(packet => packet.Split("<linia>").Skip(1)), line => Assert.True(line.IndexOf("</linia>", StringComparison.Ordinal) <= NovitusClient.NonFiscalLineWidth));
    }

    private enum Scenario { Success, RejectClose, DropAfterClose }

    private sealed record Run(PrintResult Result, List<string> Packets);

    private static FiscalJob Job(FiscalReceiptPayload receipt, int port = 1, string environment = "production") =>
        new("test", "POS/1", environment, "novitus:loopback", "Emulator Novitus", "127.0.0.1", port, null, receipt, FiscalProtocols.Novitus);

    // Wyłącznie emulacja na loopbacku: bez prawdziwego urządzenia, bazy i sprzedaży.
    private static async Task<Run> RunAsync(Scenario scenario, string deviceInfo = DeviceInfo, string environment = "production")
    {
        Encoding.RegisterProvider(CodePagesEncodingProvider.Instance);
        var encoding = Encoding.GetEncoding(1250);
        using var listener = new TcpListener(IPAddress.Loopback, 0);
        listener.Start();
        var port = ((IPEndPoint)listener.LocalEndpoint).Port;
        using var timeout = new CancellationTokenSource(TimeSpan.FromSeconds(10));
        var packets = new List<string>();
        var server = Task.Run(async () =>
        {
            using var socket = await listener.AcceptTcpClientAsync(timeout.Token);
            using var stream = socket.GetStream();
            var text = new StringBuilder(); var buffer = new byte[4096];
            var open = false; var lastNumber = 41; var lastError = "0"; var lastOk = true;
            while (true)
            {
                var read = await stream.ReadAsync(buffer, timeout.Token);
                if (read == 0) return;
                text.Append(encoding.GetString(buffer, 0, read));
                int end;
                while ((end = text.ToString().IndexOf("</pakiet>", StringComparison.Ordinal)) >= 0)
                {
                    var packet = text.ToString()[..(end + 9)]; text.Remove(0, end + 9); packets.Add(packet);
                    string? reply = null;
                    if (packet.Contains("<dle_pl")) reply = "<dle_pl online=\"tak\" brak_papieru=\"nie\" blad_urzadzenia=\"nie\" />";
                    else if (packet.Contains("<enq_pl")) reply = $"<enq_pl fiskalna=\"tak\" ostatni_rozkaz_ok=\"{(lastOk ? "tak" : "nie")}\" tryb_transakcji=\"{(open ? "tak" : "nie")}\" ostatnia_transakcja_ok=\"tak\" />";
                    else if (packet.Contains("akcja=\"urzadzenie\"")) { await stream.WriteAsync(encoding.GetBytes(deviceInfo), timeout.Token); continue; }
                    else if (packet.Contains("akcja=\"ostatnia_transakcja\"")) reply = $"<informacja akcja=\"ostatnia_transakcja\" typ=\"paragon\" stan=\"zamknij\" numer=\"{lastNumber}\" data=\"02-10-2026 09:{lastNumber:00}\" />";
                    else if (packet.Contains("<blad akcja=\"odczytaj\"")) reply = $"<blad akcja=\"odczytaj\" wartosc=\"{lastError}\" />";
                    else
                    {
                        if (packet.Contains("akcja=\"poczatek\"")) open = true;
                        if (packet.Contains("akcja=\"anuluj\"")) open = false;
                        if (packet.Contains("<wydruk_niefiskalny")) lastOk = true;
                        if (packet.Contains("akcja=\"zamknij\""))
                        {
                            if (scenario == Scenario.DropAfterClose) return;
                            if (scenario == Scenario.RejectClose) { lastError = "27"; lastOk = false; }
                            else { open = false; lastNumber++; }
                        }
                    }
                    if (reply is not null) await stream.WriteAsync(encoding.GetBytes("<pakiet>" + reply + "</pakiet>"), timeout.Token);
                }
            }
        }, timeout.Token);
        var items = new[] { "23", "8", "5", "0", "zw" }.Select(vat => new FiscalReceiptItem("Towar " + vat, 1, 100, vat)).ToArray();
        var job = Job(new FiscalReceiptPayload(1, "PAR/1", "PLN", 500, items, 8, "Przelew", "5260250274", Rates), port, environment);
        var result = await new FiscalReceiptService().PrintAsync(job, timeout.Token);
        await server;
        return new Run(result, packets);
    }
}
