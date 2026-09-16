using Avalonia;
using Avalonia.Controls;
using Avalonia.Layout;
using Avalonia.Threading;

namespace AltreoPrintAgent;

public sealed class MainWindow : Window
{
    private readonly AgentController _controller;
    private readonly TextBox _serverUrl = new();
    private readonly TextBox _token = new() { PasswordChar = '●' };
    private readonly TextBox _stationName = new();
    private readonly NumericUpDown _pollSeconds = new() { Minimum = 2, Maximum = 300, Increment = 1 };
    private readonly CheckBox _startWithSystem = new() { Content = "Uruchamiaj razem z systemem" };
    private readonly CheckBox _allowHttp = new() { Content = "Zezwalaj na HTTP (tylko testy lokalne)" };
    private readonly TextBox _sumatraPath = new() { Watermark = "Opcjonalnie, np. C:\\Program Files\\SumatraPDF\\SumatraPDF.exe" };
    private readonly TextBox _fiscalHost = new() { Watermark = "Np. 192.168.1.15" };
    private readonly NumericUpDown _fiscalPort = new() { Minimum = 1, Maximum = 65535, Increment = 1 };
    private readonly TextBlock _state = new() { TextWrapping = Avalonia.Media.TextWrapping.Wrap };
    private readonly TextBox _log = new() { IsReadOnly = true, AcceptsReturn = true, TextWrapping = Avalonia.Media.TextWrapping.Wrap, Height = 150 };
    private bool _allowClose;

    public MainWindow(AgentController controller)
    {
        _controller = controller;
        Title = BuildProfile.DisplayName;
        Width = 590;
        Height = 700;
        MinWidth = 500;
        MinHeight = 600;
        Icon = IconFactory.Create();
        WindowStartupLocation = WindowStartupLocation.CenterScreen;
        Content = BuildContent();
        LoadSettings();
        _controller.StateChanged += OnStateChanged;
        Closing += (_, eventArgs) =>
        {
            if (_allowClose) return;
            eventArgs.Cancel = true;
            Hide();
        };
    }

    private Control BuildContent()
    {
        var save = new Button { Content = "Zapisz i uruchom", HorizontalAlignment = HorizontalAlignment.Left };
        save.Click += async (_, _) => await SaveAsync();
        var test = new Button { Content = "Testuj połączenie" };
        test.Click += async (_, _) => await TestAsync();
        var testFiscal = new Button { Content = "Drukuj test niefiskalny Posnet" };
        testFiscal.Click += async (_, _) => await TestFiscalAsync();

        var buttons = new StackPanel { Orientation = Orientation.Horizontal, Spacing = 10, Children = { save, test } };
        var panel = new StackPanel
        {
            Margin = new Thickness(24),
            Spacing = 8,
            Children =
            {
                new TextBlock { Text = "Altreo Print Agent", FontSize = 26, FontWeight = Avalonia.Media.FontWeight.SemiBold },
                new TextBlock { Text = "Automatyczne drukowanie z magazyn.altreo.pl · " + BuildProfile.Name.ToUpperInvariant(), Opacity = 0.7, Margin = new Thickness(0, 0, 0, 12) },
                Label("Adres API serwera"), _serverUrl,
                Label("Token stanowiska"), _token,
                Label("Nazwa stanowiska"), _stationName,
                Label("Polling (sekundy)"), _pollSeconds,
                _startWithSystem,
                _allowHttp,
                Label("Ścieżka SumatraPDF.exe (Windows, opcjonalna)"), _sumatraPath,
                buttons,
                new Border { Height = 1, Background = Avalonia.Media.Brushes.LightGray, Margin = new Thickness(0, 10) },
                new TextBlock { Text = "Drukarka fiskalna Posnet", FontSize = 18, FontWeight = Avalonia.Media.FontWeight.SemiBold },
                new TextBlock { Text = "Adres i port usługi Interfejs PC ustawionej w drukarce. Test poniżej jest zawsze niefiskalny.", Opacity = 0.7 },
                Label("Adres IP / host Posnet"), _fiscalHost,
                Label("Port Posnet"), _fiscalPort,
                testFiscal,
                new Border { Height = 1, Background = Avalonia.Media.Brushes.LightGray, Margin = new Thickness(0, 10) },
                new TextBlock { Text = "Stan", FontWeight = Avalonia.Media.FontWeight.SemiBold },
                _state,
                new TextBlock { Text = "Ostatnie zdarzenia", FontWeight = Avalonia.Media.FontWeight.SemiBold, Margin = new Thickness(0, 8, 0, 0) },
                _log,
                new TextBlock { Text = "Zamknięcie okna chowa aplikację do traya.", Opacity = 0.65 }
            }
        };
        return new ScrollViewer { Content = panel };
    }

    private static TextBlock Label(string text) => new() { Text = text, FontWeight = Avalonia.Media.FontWeight.Medium };

    private void LoadSettings()
    {
        var settings = _controller.Settings;
        _serverUrl.Text = settings.ServerUrl;
        _token.Text = settings.StationToken;
        _stationName.Text = settings.StationName;
        _pollSeconds.Value = settings.PollSeconds;
        _startWithSystem.IsChecked = settings.StartWithSystem;
        _allowHttp.IsChecked = settings.AllowInsecureHttp;
        _sumatraPath.Text = settings.SumatraPath;
        _fiscalHost.Text = settings.FiscalPrinterHost;
        _fiscalPort.Value = settings.FiscalPrinterPort;
        _state.Text = settings.IsConfigured ? "Konfiguracja wczytana. Agent łączy się z serwerem." : "Uzupełnij adres serwera i token.";
    }

    private AgentSettings ReadSettings() => new()
    {
        ServerUrl = (_serverUrl.Text ?? "").Trim(),
        StationToken = (_token.Text ?? "").Trim(),
        StationName = (_stationName.Text ?? "").Trim(),
        PollSeconds = Convert.ToInt32(_pollSeconds.Value ?? 5),
        StartWithSystem = _startWithSystem.IsChecked == true,
        AllowInsecureHttp = _allowHttp.IsChecked == true,
        SumatraPath = string.IsNullOrWhiteSpace(_sumatraPath.Text) ? null : _sumatraPath.Text.Trim(),
        FiscalPrinterHost = (_fiscalHost.Text ?? "").Trim(),
        FiscalPrinterPort = Convert.ToInt32(_fiscalPort.Value ?? 6666)
    };

    private async Task SaveAsync()
    {
        try
        {
            _state.Text = "Zapisywanie…";
            await _controller.SaveAsync(ReadSettings());
            _state.Text = "Zapisano. Agent działa w tle.";
        }
        catch (Exception exception)
        {
            _state.Text = "Błąd: " + exception.Message;
        }
    }

    private async Task TestAsync()
    {
        try
        {
            _state.Text = "Sprawdzanie połączenia…";
            using var cancellation = new CancellationTokenSource(TimeSpan.FromSeconds(20));
            await _controller.TestConnectionAsync(ReadSettings(), cancellation.Token);
            _state.Text = "Połączenie działa, a token został zaakceptowany.";
        }
        catch (Exception exception)
        {
            _state.Text = "Test nieudany: " + exception.Message;
        }
    }

    private async Task TestFiscalAsync()
    {
        try
        {
            _state.Text = "Łączenie z Posnet i wykonywanie testu niefiskalnego…";
            using var cancellation = new CancellationTokenSource(TimeSpan.FromSeconds(30));
            var settings = ReadSettings();
            var result = await _controller.TestFiscalPrinterAsync(settings, cancellation.Token);
            if (result.Status != JobStatuses.Printed) throw new InvalidOperationException(result.Message);
            _state.Text = result.Message;
        }
        catch (Exception exception)
        {
            _state.Text = "Test Posnet nieudany: " + exception.Message;
        }
    }

    private void OnStateChanged(string line) => Dispatcher.UIThread.Post(() =>
    {
        _state.Text = line;
        var lines = ((_log.Text ?? "") + line + Environment.NewLine).Split(Environment.NewLine);
        _log.Text = string.Join(Environment.NewLine, lines.TakeLast(100));
        _log.CaretIndex = _log.Text.Length;
    });

    public void AllowClose() => _allowClose = true;
}
