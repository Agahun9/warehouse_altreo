namespace AltreoPrintAgent;

public sealed class AgentController : IAsyncDisposable
{
    private readonly SettingsStore _store;
    private readonly SemaphoreSlim _restartLock = new(1, 1);
    private CancellationTokenSource? _workerCancellation;
    private Task? _workerTask;

    public AgentSettings Settings { get; private set; }
    public AgentLog Log { get; }
    public event Action<string>? StateChanged;

    public AgentController()
    {
        _store = new SettingsStore();
        Settings = _store.Load();
        Log = new AgentLog(_store.DirectoryPath);
        Log.LineWritten += line => StateChanged?.Invoke(line);
    }

    public async Task StartAsync()
    {
        if (!Settings.IsConfigured)
        {
            StateChanged?.Invoke("Wymagana konfiguracja serwera i tokenu.");
            return;
        }
        await RestartWorkerAsync();
    }

    public async Task SaveAsync(AgentSettings settings)
    {
        PrintAgentApi.ValidateServerUrl(settings);
        if (string.IsNullOrWhiteSpace(settings.StationName)) throw new InvalidOperationException("Nazwa stanowiska jest pusta.");
        if (settings.PollSeconds is < 2 or > 300) throw new InvalidOperationException("Interwał pollingu musi mieścić się między 2 a 300 sekund.");
        PosnetClient.ValidateEndpoint(settings.FiscalPrinterHost, settings.FiscalPrinterPort);

        using (var cancellation=new CancellationTokenSource(TimeSpan.FromSeconds(20)))
        {
            await new PrintAgentApi(settings).TestAsync(cancellation.Token);
        }

        _store.Save(settings);
        StartupService.Configure(settings.StartWithSystem);
        Settings = settings;
        await RestartWorkerAsync();
        Log.Info("Konfiguracja została zapisana.");
    }

    public async Task TestConnectionAsync(AgentSettings settings, CancellationToken cancellationToken)
    {
        var api = new PrintAgentApi(settings);
        await api.TestAsync(cancellationToken);
    }

    public async Task<PrintResult> TestFiscalPrinterAsync(AgentSettings settings, CancellationToken cancellationToken)
    {
        PosnetClient.ValidateEndpoint(settings.FiscalPrinterHost, settings.FiscalPrinterPort);
        return await new FiscalReceiptService().TestNonFiscalAsync(settings.FiscalPrinterHost, settings.FiscalPrinterPort, cancellationToken);
    }

    private async Task RestartWorkerAsync()
    {
        await _restartLock.WaitAsync();
        try
        {
            if (_workerCancellation is not null)
            {
                await _workerCancellation.CancelAsync();
                if (_workerTask is not null)
                {
                    try { await _workerTask; } catch (OperationCanceledException) { }
                }
                _workerCancellation.Dispose();
            }

            _workerCancellation = new CancellationTokenSource();
            var worker = new PollingWorker(Settings, Log);
            _workerTask = Task.Run(() => worker.RunAsync(_workerCancellation.Token));
        }
        finally
        {
            _restartLock.Release();
        }
    }

    public async ValueTask DisposeAsync()
    {
        if (_workerCancellation is null) return;
        await _workerCancellation.CancelAsync();
        if (_workerTask is not null)
        {
            try { await _workerTask; } catch (OperationCanceledException) { }
        }
        _workerCancellation.Dispose();
        _restartLock.Dispose();
    }
}
