using Avalonia.Controls;

namespace AltreoPrintAgent;

internal static class IconFactory
{
    public static WindowIcon Create()
    {
        const int size = 32;
        using var stream = new MemoryStream();
        using (var writer = new BinaryWriter(stream, System.Text.Encoding.UTF8, true))
        {
            writer.Write((ushort)0); // reserved
            writer.Write((ushort)1); // icon
            writer.Write((ushort)1); // one image
            writer.Write((byte)size);
            writer.Write((byte)size);
            writer.Write((byte)0);
            writer.Write((byte)0);
            writer.Write((ushort)1);
            writer.Write((ushort)32);
            var imageBytes = 40 + size * size * 4 + size * 4;
            writer.Write(imageBytes);
            writer.Write(22); // image offset

            writer.Write(40); // BITMAPINFOHEADER
            writer.Write(size);
            writer.Write(size * 2); // color + mask
            writer.Write((ushort)1);
            writer.Write((ushort)32);
            writer.Write(0);
            writer.Write(size * size * 4);
            writer.Write(0);
            writer.Write(0);
            writer.Write(0);
            writer.Write(0);

            for (var y = size - 1; y >= 0; y--)
                for (var x = 0; x < size; x++)
                {
                    var paper = x is >= 9 and <= 22 && (y is >= 5 and <= 12 || y is >= 20 and <= 27);
                    var printer = x is >= 5 and <= 26 && y is >= 11 and <= 22;
                    var white = paper || (printer && !(x is >= 9 and <= 22 && y is >= 15 and <= 19));
                    writer.Write(white ? (byte)245 : (byte)145); // B
                    writer.Write(white ? (byte)245 : (byte)87);  // G
                    writer.Write(white ? (byte)245 : (byte)27);  // R
                    writer.Write((byte)255);
                }
            writer.Write(new byte[size * 4]); // transparency mask
        }
        stream.Position = 0;
        return new WindowIcon(stream);
    }
}
