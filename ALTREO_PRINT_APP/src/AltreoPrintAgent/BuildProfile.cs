namespace AltreoPrintAgent;

public static class BuildProfile
{
#if SANDBOX
    public static string Name => "sandbox";
#else
    public static string Name => "production";
#endif
    public static string DisplayName => Name=="sandbox"?"Altreo Print Agent Sandbox":"Altreo Print Agent";
    public static string Identifier => Name=="sandbox"?"pl.altreo.printagent.sandbox":"pl.altreo.printagent";
}
