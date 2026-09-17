using Avalonia;
using Avalonia.Controls;
using Avalonia.Controls.ApplicationLifetimes;
using Avalonia.Markup.Xaml;

namespace AltreoPrintAgent;

public sealed partial class App : Application
{
    private AgentController? _controller;
    private MainWindow? _window;
    private TrayIcon? _trayIcon;

    public override void Initialize() => AvaloniaXamlLoader.Load(this);

    public override void OnFrameworkInitializationCompleted()
    {
        if (ApplicationLifetime is IClassicDesktopStyleApplicationLifetime desktop)
        {
            desktop.ShutdownMode = ShutdownMode.OnExplicitShutdown;
            _controller = new AgentController();
            _window = new MainWindow(_controller);
            desktop.MainWindow = _window;
            CreateTray(desktop);

            var background = desktop.Args?.Contains("--background", StringComparer.OrdinalIgnoreCase) == true;
            if (!background || !_controller.Settings.IsConfigured) _window.Show();
            _ = StartControllerAsync();
        }
        base.OnFrameworkInitializationCompleted();
    }

    private async Task StartControllerAsync()
    {
        try
        {
            if (_controller is not null) await _controller.StartAsync();
        }
        catch (Exception exception)
        {
            _controller?.Log.Error("Nie można uruchomić agenta: " + exception.Message);
            _window?.Show();
        }
    }

    private void CreateTray(IClassicDesktopStyleApplicationLifetime desktop)
    {
        var open = new NativeMenuItem("Otwórz ustawienia");
        open.Click += (_, _) => ShowWindow();
        var exit = new NativeMenuItem("Zakończ");
        exit.Click += async (_, _) =>
        {
            if (_controller is not null) await _controller.DisposeAsync();
            _window?.AllowClose();
            _trayIcon?.Dispose();
            desktop.Shutdown();
        };
        var menu = new NativeMenu { Items = { open, new NativeMenuItemSeparator(), exit } };
        _trayIcon = new TrayIcon
        {
            Icon = IconFactory.Create(),
            ToolTipText = BuildProfile.DisplayName,
            Menu = menu,
            Command = new ActionCommand(ShowWindow),
            IsVisible = true
        };
        TrayIcon.SetIcons(this, new TrayIcons { _trayIcon });
    }

    private void ShowWindow()
    {
        if (_window is null) return;
        _window.Show();
        _window.Activate();
    }
}
