using System.Net;
using System.Net.Sockets;
using System.Text;
using Xunit;

namespace AltreoPrintAgent.Tests;

public sealed class FiscalTests
{
    private const string VatResponse="vatget\tva23.00\tvb8.00\tvc7.00\tvd5.00\tve0.00\tvf0.00\tvg100.00\t";
    private static readonly Dictionary<string,string> Rates=new() { ["A"]="23",["B"]="8",["C"]="7",["D"]="5",["E"]="0",["F"]="0",["G"]="zw" };

    [Fact]
    public void VatRatesKeepFirstZeroAndSeparateExemption()
    {
        var rates=PosnetClient.ParseVatRates(VatResponse,Rates);
        Assert.Equal(0,rates["23"]); Assert.Equal(1,rates["8"]); Assert.Equal(2,rates["7"]);
        Assert.Equal(3,rates["5"]); Assert.Equal(4,rates["0"]); Assert.Equal(6,rates["zw"]);
    }

    [Fact]
    public void ActualDeviceRatesHandleInactiveSlots()
    {
        var expected=new Dictionary<string,string> { ["A"]="23",["B"]="8",["C"]="5",["D"]="0",["E"]="zw",["F"]="nieaktywna",["G"]="nieaktywna" };
        var rates=PosnetClient.ParseVatRates("vatget\tva23,00\tvb8,00\tvc5,00\tvd0,00\tve100,00\tvf101,00\tvg101,00\t",expected);
        Assert.Equal(2,rates["5"]); Assert.Equal(3,rates["0"]); Assert.Equal(4,rates["zw"]);
        Assert.False(rates.ContainsKey("7")); Assert.False(rates.ContainsKey("nieaktywna"));
    }

    [Fact]
    public void MismatchedAndIncompleteRatesAreRejected()
    {
        Assert.Throws<InvalidOperationException>(()=>PosnetClient.ParseVatRates(VatResponse.Replace("vc7.00","vc5.00"),Rates));
        Assert.Throws<InvalidOperationException>(()=>PosnetClient.ParseVatRates("vatget\tva23.00\t",Rates));
    }

    [Fact]
    public async Task FiscalProtocolCarriesAllVatLettersAmountsPaymentAndNip()
    {
        var commands=await RunPrinterAsync(VatResponse,false,true);
        var lines=commands.Where(command=>command.StartsWith("trline\t")).ToArray();
        Assert.Equal(6,lines.Length);
        foreach (var index in new[] {0,1,2,3,4,6}) Assert.Contains(lines,line=>line.Contains($"\tvt{index}\t") && line.Contains("\tpr100\til1\t"));
        Assert.Contains("trnipset\tni5260250274\t",commands);
        Assert.Contains("trpayment\tty8\twa600\tnaPrzelew\t",commands);
        Assert.Contains("trend\tto600\tfp600\t",commands);
        Assert.DoesNotContain(commands,command=>command.StartsWith("prncancel"));
    }

    [Fact]
    public async Task VatMismatchDoesNotStartTransaction()
    {
        var commands=await RunPrinterAsync(VatResponse.Replace("vc7.00","vc5.00"),false,false);
        Assert.DoesNotContain(commands,command=>command.StartsWith("trinit") || command.StartsWith("trline"));
    }

    [Fact]
    public async Task LostFinalReplyNeverRetriesOrCancelsReceipt()
    {
        var commands=await RunPrinterAsync(VatResponse,true,false);
        Assert.Single(commands,command=>command.StartsWith("trend"));
        Assert.DoesNotContain(commands,command=>command.StartsWith("prncancel"));
    }

    // Only loopback emulation: no real device, database, or sale.
    private static async Task<List<string>> RunPrinterAsync(string vatResponse,bool dropFinalReply,bool expectSuccess)
    {
        using var listener=new TcpListener(IPAddress.Loopback,0);
        listener.Start();
        var port=((IPEndPoint)listener.LocalEndpoint).Port;
        using var timeout=new CancellationTokenSource(TimeSpan.FromSeconds(10));
        var commands=new List<string>();
        var server=Task.Run(async ()=>
        {
            using var socket=await listener.AcceptTcpClientAsync(timeout.Token);
            using var stream=socket.GetStream();
            var bytes=new List<byte>(); var one=new byte[1];
            while (await stream.ReadAsync(one,timeout.Token)>0)
            {
                if (one[0]==2) { bytes.Clear(); continue; }
                if (one[0]!=3) { bytes.Add(one[0]); continue; }
                var frame=Encoding.ASCII.GetString(bytes.ToArray());
                var body=frame[..frame.LastIndexOf('#')]; commands.Add(body);
                var command=body.Split('\t')[0];
                if (command=="trend" && dropFinalReply) break;
                var reply=command switch { "sdev"=>"sdev\tds0\t", "sprn"=>"sprn\tpr0\t", "scomm"=>"scomm\tts0\t", "vatget"=>vatResponse, "trend"=>"trend\tnr123\t", _=>command+"\t" };
                var data=Encoding.ASCII.GetBytes(reply);
                var response=Encoding.ASCII.GetBytes("\x02"+reply+"#"+PosnetClient.CalculateCrc(data).ToString("X4")+"\x03");
                await stream.WriteAsync(response,timeout.Token);
            }
        },timeout.Token);
        var items=new[] {"23","8","7","5","0","zw"}.Select(vat=>new FiscalReceiptItem("Towar "+vat,1,100,vat)).ToArray();
        var job=new FiscalJob("test","POS/1","production","tcp:loopback","Emulator","127.0.0.1",port,null,new FiscalReceiptPayload(1,"PAR/1","PLN",600,items,8,"Przelew","5260250274",Rates));
        var result=await new FiscalReceiptService().PrintAsync(job,timeout.Token);
        await server;
        Assert.Equal(expectSuccess?JobStatuses.Printed:JobStatuses.Error,result.Status);
        if (expectSuccess) Assert.Equal("123",result.Reference);
        return commands;
    }
}
