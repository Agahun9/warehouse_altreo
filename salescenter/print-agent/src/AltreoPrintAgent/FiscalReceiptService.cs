using System.Net.Sockets;

namespace AltreoPrintAgent;

public sealed class FiscalReceiptService
{
    public async Task<PrintResult> PrintAsync(FiscalJob job,CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        if (job.Environment is not ("sandbox" or "production") || (job.Environment=="production" && BuildProfile.Name!="production"))
            return PrintResult.Error($"Agent {BuildProfile.Name} nie obsługuje zadania w trybie {job.Environment}.");
        if (job.Receipt.Items.Count==0 || job.Receipt.TotalCents<0)
            return PrintResult.Error("Paragon nie zawiera poprawnych pozycji.");
        if (!string.Equals(job.Receipt.Currency,"PLN",StringComparison.OrdinalIgnoreCase))
            return PrintResult.Error("Automatyczna fiskalizacja obsługuje wyłącznie walutę PLN.");
        if (job.Receipt.PaymentType is not (0 or 2 or 3 or 4 or 5 or 6 or 7 or 8))
            return PrintResult.Error("Paragon zawiera nieobsługiwaną formę płatności.");
        if (job.Receipt.Items.Any(item=>string.IsNullOrWhiteSpace(item.Name) || item.Quantity<1 || item.UnitCents<0 || !new[] { "23","8","7","5","0","zw" }.Contains(item.Vat,StringComparer.OrdinalIgnoreCase)))
            return PrintResult.Error("Paragon zawiera nieprawidłową pozycję, ilość, cenę albo stawkę VAT.");
        var calculated=job.Receipt.Items.Sum(item=>checked(item.UnitCents*item.Quantity));
        if (calculated!=job.Receipt.TotalCents)
            return PrintResult.Error($"Suma pozycji ({calculated}) nie zgadza się z kwotą zamówienia ({job.Receipt.TotalCents}).");
        try
        {
            await using var client = CreateClient(job.Protocol);
            await client.ConnectAsync(job.Host, job.Port, cancellationToken);
            if (job.Environment=="sandbox")
            {
                await client.PrintNonFiscalReceiptAsync(job, cancellationToken);
                return PrintResult.Printed($"{client.DeviceLabel} potwierdził wydruk paragonu niefiskalnego (sandbox); sprzedaż nie została zafiskalizowana.");
            }
            var fiscalNumber = await client.PrintFiscalReceiptAsync(job, cancellationToken);
            var suffix = string.IsNullOrWhiteSpace(fiscalNumber) ? "" : $" Numer urządzenia: {fiscalNumber}.";
            return PrintResult.Printed($"Drukarka {client.DeviceLabel} potwierdziła zakończenie paragonu fiskalnego." + suffix, fiscalNumber);
        }
        catch (InvalidOperationException exception)
        {
            return PrintResult.Error(exception.Message);
        }
        catch (SocketException exception)
        {
            return PrintResult.Offline(exception.Message);
        }
        catch (TimeoutException exception)
        {
            return PrintResult.Offline(exception.Message);
        }
        catch (IOException exception)
        {
            return PrintResult.Error(exception.Message);
        }
        catch (InvalidDataException exception)
        {
            return PrintResult.Error(exception.Message);
        }
    }

    public static IFiscalPrinterClient CreateClient(string? protocol) =>
        FiscalProtocols.Normalize(protocol)==FiscalProtocols.Novitus ? new NovitusClient() : new PosnetClient();

    public async Task<PrintResult> TestNonFiscalAsync(string host,int port,CancellationToken cancellationToken,string? protocol=null)
    {
        try
        {
            await using var client=CreateClient(protocol);
            await client.ConnectAsync(host,port,cancellationToken);
            return PrintResult.Printed(await client.TestNonFiscalAsync(cancellationToken));
        }
        catch (SocketException exception) { return PrintResult.Offline(exception.Message); }
        catch (TimeoutException exception) { return PrintResult.Offline(exception.Message); }
        catch (IOException exception) { return PrintResult.Error(exception.Message); }
        catch (InvalidDataException exception) { return PrintResult.Error(exception.Message); }
    }
}
