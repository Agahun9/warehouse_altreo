using Xunit;

namespace AltreoPrintAgent.Tests;

public sealed class AgentTests
{
    [Fact]
    public void BaseUrlAlwaysEndsWithSlash()
    {
        var uri = PrintAgentApi.NormalizeBaseUri("https://magazyn.altreo.pl/api/print-agent");
        Assert.Equal("https://magazyn.altreo.pl/api/print-agent/", uri.AbsoluteUri);
    }

    [Fact]
    public void PhpApiEntryPointKeepsFileUrl()
    {
        var uri=PrintAgentApi.NormalizeBaseUri("https://magazyn.altreo.pl/crm/new_version/print-agent-api.php/");
        Assert.Equal("https://magazyn.altreo.pl/crm/new_version/print-agent-api.php",uri.AbsoluteUri);
    }

    [Fact]
    public void HttpIsRejectedByDefault()
    {
        var settings = new AgentSettings { ServerUrl = "http://example.test/api/", StationToken = "secret" };
        var exception = Assert.Throws<InvalidOperationException>(() => PrintAgentApi.ValidateServerUrl(settings));
        Assert.Contains("HTTPS", exception.Message);
    }

    [Fact]
    public void HttpCanBeEnabledForLocalTesting()
    {
        var settings = new AgentSettings
        {
            ServerUrl = "http://127.0.0.1:8080/api/",
            StationToken = "secret",
            AllowInsecureHttp = true
        };
        PrintAgentApi.ValidateServerUrl(settings);
    }

    [Fact]
    public async Task ProcessRunnerDoesNotUseShellInterpolation()
    {
        if (OperatingSystem.IsWindows()) return;
        var value = "hello; echo unsafe";
        var result = await ProcessRunner.RunAsync("/bin/echo", [value], TimeSpan.FromSeconds(5), CancellationToken.None);
        Assert.Equal(0, result.ExitCode);
        Assert.Equal(value, result.StandardOutput.Trim());
    }

    [Fact]
    public async Task UnsupportedPlatformReturnsReadableError()
    {
        if (OperatingSystem.IsWindows() || OperatingSystem.IsMacOS()) return;
        var service = new PrintService(new AgentSettings());
        var temporary = Path.GetTempFileName();
        try
        {
            var result = await service.PrintAsync(temporary, "printer", null, CancellationToken.None);
            Assert.Equal(JobStatuses.Error, result.Status);
        }
        finally
        {
            File.Delete(temporary);
        }
    }

    [Fact]
    public void ParsesCupsPrinterInventory()
    {
        var printers=PrinterDiscovery.ParseCupsPrinters("printer Zebra_ZD421 is idle. enabled since today\ndrukarka Office jest bezczynna\n");
        Assert.Equal(["Zebra_ZD421","Office"],printers);
        Assert.Equal("Zebra_ZD421",PrinterDiscovery.ParseCupsDefault("system default destination: Zebra_ZD421\n"));
        Assert.Equal("Office",PrinterDiscovery.ParseCupsDefault("domyślny cel systemowy: Office\n"));
    }

    [Fact]
    public void ConvertsCustomMillimetresToCupsMedia()
    {
        Assert.Equal("Custom.100x150mm",PrintService.ParseCustomPaperForCups("fit,paper=100mm x 150mm"));
        Assert.Null(PrintService.ParseCustomPaperForCups("fit,paper=A4"));
    }

    [Fact]
    public void ParsesDetectedPosnetNetworkPrinters()
    {
        var devices=PrinterDiscovery.ParseFiscalPrinterLines("Posnet Trio\t192.168.1.45\t9100\n");
        Assert.Single(devices);
        Assert.Equal("tcp:192.168.1.45:9100",devices[0].DeviceKey);
    }

    [Theory]
    [InlineData("trinit\t", "911D")]
    [InlineData("trinit\tbm0\t", "4825")]
    [InlineData("rtcget\t", "7D61")]
    public void CalculatesPosnetProtocolCrc(string body,string expected)
    {
        var bytes=System.Text.Encoding.ASCII.GetBytes(body);
        Assert.Equal(expected,PosnetClient.CalculateCrc(bytes).ToString("X4"));
    }

    [Fact]
    public void ParsesPosnetResponseProperties()
    {
        Assert.Equal("0",PosnetClient.FirstProperty("sdev\tds0\t", "ds"));
        Assert.Null(PosnetClient.FirstProperty("sdev\tds0\t", "pr"));
    }

    [Fact]
    public async Task MissingOptionalFiscalEndpointIsRemembered()
    {
        var handler=new CountingHandler(_=>new HttpResponseMessage(System.Net.HttpStatusCode.NotFound));
        var api=new PrintAgentApi(new AgentSettings { ServerUrl="https://example.test/print-agent-api.php",StationToken="token" },handler);
        Assert.Null(await api.GetNextFiscalJobAsync(CancellationToken.None));
        Assert.Null(await api.GetNextFiscalJobAsync(CancellationToken.None));
        Assert.Equal(1,handler.Count);
    }

    private sealed class CountingHandler(Func<HttpRequestMessage,HttpResponseMessage> response):HttpMessageHandler
    {
        public int Count { get; private set; }
        protected override Task<HttpResponseMessage> SendAsync(HttpRequestMessage request,CancellationToken cancellationToken)
        {
            Count++;
            return Task.FromResult(response(request));
        }
    }
}
