using System.Text.Json.Serialization;

namespace AltreoPrintAgent;

public sealed record AgentSettings
{
    public string ServerUrl { get; init; } = "https://magazyn.altreo.pl/crm/new_version/print-agent-api.php";
    public string StationToken { get; init; } = "";
    public string StationName { get; init; } = Environment.MachineName;
    public int PollSeconds { get; init; } = 5;
    public bool StartWithSystem { get; init; } = true;
    public bool AllowInsecureHttp { get; init; }
    public string? SumatraPath { get; init; }
    public string FiscalPrinterHost { get; init; } = "192.168.1.15";
    public int FiscalPrinterPort { get; init; } = 6666;

    [JsonIgnore]
    public bool IsConfigured => Uri.TryCreate(ServerUrl, UriKind.Absolute, out _) && !string.IsNullOrWhiteSpace(StationToken);

    [JsonIgnore]
    public bool IsFiscalPrinterConfigured => !string.IsNullOrWhiteSpace(FiscalPrinterHost) && FiscalPrinterPort is >= 1 and <= 65535;
}

public sealed record PrintJob(
    [property: JsonPropertyName("id")] string Id,
    [property: JsonPropertyName("pdfUrl")] string PdfUrl,
    [property: JsonPropertyName("printerName")] string PrinterName,
    [property: JsonPropertyName("printSettings")] string? PrintSettings = null);

public sealed record FiscalReceiptItem(
    [property: JsonPropertyName("name")] string Name,
    [property: JsonPropertyName("quantity")] int Quantity,
    [property: JsonPropertyName("unitCents")] long UnitCents,
    [property: JsonPropertyName("vat")] string Vat);

public sealed record FiscalReceiptPayload(
    [property: JsonPropertyName("orderId")] long OrderId,
    [property: JsonPropertyName("orderNumber")] string OrderNumber,
    [property: JsonPropertyName("currency")] string Currency,
    [property: JsonPropertyName("totalCents")] long TotalCents,
    [property: JsonPropertyName("items")] IReadOnlyList<FiscalReceiptItem> Items,
    [property: JsonPropertyName("paymentType")] int PaymentType = 6,
    [property: JsonPropertyName("paymentName")] string PaymentName = "Płatność online",
    [property: JsonPropertyName("buyerNip")] string? BuyerNip = null);

public sealed record FiscalJob(
    [property: JsonPropertyName("id")] string Id,
    [property: JsonPropertyName("localNumber")] string LocalNumber,
    [property: JsonPropertyName("environment")] string Environment,
    [property: JsonPropertyName("deviceKey")] string DeviceKey,
    [property: JsonPropertyName("printerName")] string PrinterName,
    [property: JsonPropertyName("host")] string Host,
    [property: JsonPropertyName("port")] int Port,
    [property: JsonPropertyName("serialNumber")] string? SerialNumber,
    [property: JsonPropertyName("receipt")] FiscalReceiptPayload Receipt);

public static class JobStatuses
{
    public const string Processing = "processing";
    public const string Printed = "printed";
    public const string Error = "error";
    public const string PrinterOffline = "printer_offline";
}

public sealed record PrintResult(string Status, string Message, string? Reference = null)
{
    public static PrintResult Printed(string message = "Zadanie przekazane do kolejki drukarki.", string? reference = null) => new(JobStatuses.Printed, message, reference);
    public static PrintResult Error(string message) => new(JobStatuses.Error, message);
    public static PrintResult Offline(string message) => new(JobStatuses.PrinterOffline, message);
}
