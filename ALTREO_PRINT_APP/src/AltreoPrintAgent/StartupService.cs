using System.Security;
using System.Runtime.Versioning;
using Microsoft.Win32;

namespace AltreoPrintAgent;

public static class StartupService
{
    public static void Configure(bool enabled)
    {
        var executable = Environment.ProcessPath ?? throw new InvalidOperationException("Nie można ustalić ścieżki programu.");
        if (OperatingSystem.IsWindows()) ConfigureWindows(enabled, executable);
        else if (OperatingSystem.IsMacOS()) ConfigureMac(enabled, executable);
    }

    [SupportedOSPlatform("windows")]
    private static void ConfigureWindows(bool enabled, string executable)
    {
        using var key = Registry.CurrentUser.CreateSubKey(@"Software\Microsoft\Windows\CurrentVersion\Run");
        if (enabled) key.SetValue(BuildProfile.DisplayName, $"\"{executable}\" --background");
        else key.DeleteValue(BuildProfile.DisplayName, false);
    }

    private static void ConfigureMac(bool enabled, string executable)
    {
        var directory = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.UserProfile), "Library", "LaunchAgents");
        var path = Path.Combine(directory, BuildProfile.Identifier+".plist");
        if (!enabled)
        {
            if (File.Exists(path)) File.Delete(path);
            return;
        }

        Directory.CreateDirectory(directory);
        var safePath = SecurityElement.Escape(executable);
        File.WriteAllText(path, $$"""
            <?xml version="1.0" encoding="UTF-8"?>
            <!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
            <plist version="1.0"><dict>
              <key>Label</key><string>{{BuildProfile.Identifier}}</string>
              <key>ProgramArguments</key><array><string>{{safePath}}</string><string>--background</string></array>
              <key>RunAtLoad</key><true/>
              <key>KeepAlive</key><false/>
            </dict></plist>
            """);
    }
}
