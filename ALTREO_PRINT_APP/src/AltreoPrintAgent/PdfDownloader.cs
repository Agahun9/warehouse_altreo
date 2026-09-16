namespace AltreoPrintAgent;

public sealed class PdfDownloader
{
    private const long MaximumBytes = 100 * 1024 * 1024;
    private readonly HttpClient _http = new() { Timeout = TimeSpan.FromSeconds(90) };

    public async Task<string> DownloadAsync(string url, bool allowInsecureHttp, CancellationToken cancellationToken)
    {
        if (!Uri.TryCreate(url, UriKind.Absolute, out var uri) ||
            (uri.Scheme != Uri.UriSchemeHttps && !(allowInsecureHttp && uri.Scheme == Uri.UriSchemeHttp)))
            throw new InvalidDataException("Adres PDF musi być poprawnym adresem HTTPS.");

        using var response = await _http.GetAsync(uri, HttpCompletionOption.ResponseHeadersRead, cancellationToken);
        response.EnsureSuccessStatusCode();
        if (response.Content.Headers.ContentLength > MaximumBytes)
            throw new InvalidDataException("PDF przekracza limit 100 MB.");

        var path = Path.Combine(Path.GetTempPath(), $"altreo-print-{Guid.NewGuid():N}.pdf");
        try
        {
            await using var input = await response.Content.ReadAsStreamAsync(cancellationToken);
            {
                await using var output = new FileStream(path, FileMode.CreateNew, FileAccess.Write, FileShare.None, 81920, true);
                var buffer = new byte[81920];
                long total = 0;
                int read;
                while ((read = await input.ReadAsync(buffer, cancellationToken)) > 0)
                {
                    total += read;
                    if (total > MaximumBytes) throw new InvalidDataException("PDF przekracza limit 100 MB.");
                    await output.WriteAsync(buffer.AsMemory(0, read), cancellationToken);
                }
                await output.FlushAsync(cancellationToken);
                if (total < 5) throw new InvalidDataException("Pobrany plik jest pusty.");
            }

            var signature = new byte[5];
            await using (var verification = File.OpenRead(path))
                _ = await verification.ReadAsync(signature, cancellationToken);
            if (!signature.SequenceEqual("%PDF-"u8.ToArray()))
                throw new InvalidDataException("Pobrany plik nie jest dokumentem PDF.");
            return path;
        }
        catch
        {
            if (File.Exists(path)) File.Delete(path);
            throw;
        }
    }
}
