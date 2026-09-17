using Avalonia;

namespace AltreoPrintAgent;

internal static class Program
{
    [STAThread]
    public static void Main(string[] args)
    {
        if (args.Length >= 1 && string.Equals(args[0], "--test-posnet", StringComparison.OrdinalIgnoreCase))
        {
            Environment.ExitCode = RunPosnetTest(args);
            return;
        }
        if (args.Length >= 1 && string.Equals(args[0], "--test-posnet-receipt", StringComparison.OrdinalIgnoreCase))
        {
            Environment.ExitCode = RunPosnetReceiptTest(args);
            return;
        }
        using var mutex = new Mutex(true, "AltreoPrintAgent.Singleton."+BuildProfile.Name, out var isFirstInstance);
        if (!isFirstInstance) return;

        BuildAvaloniaApp().StartWithClassicDesktopLifetime(args);
        GC.KeepAlive(mutex);
    }

    private static int RunPosnetTest(string[] args)
    {
        try
        {
            var host = args.Length >= 2 ? args[1] : "192.168.1.15";
            var port = args.Length >= 3 && int.TryParse(args[2], out var parsedPort) ? parsedPort : 6666;
            using var cancellation = new CancellationTokenSource(TimeSpan.FromSeconds(30));
            var result = new FiscalReceiptService().TestNonFiscalAsync(host, port, cancellation.Token).GetAwaiter().GetResult();
            Console.WriteLine(result.Message);
            return result.Status == JobStatuses.Printed ? 0 : 1;
        }
        catch (Exception exception)
        {
            Console.Error.WriteLine(exception.Message);
            return 1;
        }
    }

    private static int RunPosnetReceiptTest(string[] args)
    {
        try
        {
            var host = args.Length >= 2 ? args[1] : "192.168.1.15";
            var port = args.Length >= 3 && int.TryParse(args[2], out var parsedPort) ? parsedPort : 6666;
            var receipt = new FiscalReceiptPayload(0,"TEST-NIEFISKALNY","PLN",100,
                [new FiscalReceiptItem("Test polaczenia ALTREO",1,100,"23")]);
            var job = new FiscalJob("test","test","sandbox","test","Posnet Trio",host,port,null,receipt);
            using var cancellation = new CancellationTokenSource(TimeSpan.FromSeconds(60));
            var result = new FiscalReceiptService().PrintAsync(job,cancellation.Token).GetAwaiter().GetResult();
            Console.WriteLine(result.Message);
            return result.Status == JobStatuses.Printed ? 0 : 1;
        }
        catch (Exception exception)
        {
            Console.Error.WriteLine(exception.Message);
            return 1;
        }
    }

    public static AppBuilder BuildAvaloniaApp() =>
        AppBuilder.Configure<App>()
            .UsePlatformDetect()
            .LogToTrace();
}
