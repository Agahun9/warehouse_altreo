using System.Text.Json;

namespace AltreoPrintAgent;

public sealed class SettingsStore
{
    private static readonly JsonSerializerOptions JsonOptions = new() { WriteIndented = true };
    public string DirectoryPath { get; } = Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.ApplicationData), "AltreoPrintAgent",BuildProfile.Name);
    public string FilePath => Path.Combine(DirectoryPath, "settings.json");

    public AgentSettings Load()
    {
        try
        {
            return File.Exists(FilePath)
                ? JsonSerializer.Deserialize<AgentSettings>(File.ReadAllText(FilePath), JsonOptions) ?? new AgentSettings()
                : new AgentSettings();
        }
        catch
        {
            return new AgentSettings();
        }
    }

    public void Save(AgentSettings settings)
    {
        Directory.CreateDirectory(DirectoryPath);
        var temporaryPath = FilePath + ".tmp";
        File.WriteAllText(temporaryPath, JsonSerializer.Serialize(settings, JsonOptions));
        File.Move(temporaryPath, FilePath, true);
    }
}
