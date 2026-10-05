namespace AltreoPrintAgent;

/// <summary>Wspólny kontrakt drukarek fiskalnych obsługiwanych przez agenta (Posnet, Novitus).</summary>
public interface IFiscalPrinterClient : IAsyncDisposable
{
    string DeviceLabel { get; }
    Task ConnectAsync(string host, int port, CancellationToken cancellationToken);
    Task<string> TestNonFiscalAsync(CancellationToken cancellationToken);
    Task PrintNonFiscalReceiptAsync(FiscalJob job, CancellationToken cancellationToken);
    Task<string?> PrintFiscalReceiptAsync(FiscalJob job, CancellationToken cancellationToken);
}
