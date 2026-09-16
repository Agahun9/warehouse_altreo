namespace AltreoPrintAgent;

public sealed class AgentLog
{
    private readonly object _lock = new();
    private readonly string _path;
    public event Action<string>? LineWritten;

    public AgentLog(string dataDirectory)
    {
        Directory.CreateDirectory(dataDirectory);
        _path = Path.Combine(dataDirectory, "agent.log");
    }

    public void Info(string message) => Write("INFO", message);
    public void Error(string message) => Write("ERROR", message);

    private void Write(string level, string message)
    {
        var line = $"{DateTimeOffset.Now:yyyy-MM-dd HH:mm:ss zzz} [{level}] {message}";
        lock (_lock)
        {
            File.AppendAllText(_path, line + Environment.NewLine);
        }
        LineWritten?.Invoke(line);
    }
}
