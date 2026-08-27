using HarmonyLib;
using System;
using System.IO;
using System.Linq;
using System.Reflection;
using System.Security.Cryptography;

namespace harmony_ssl_hook
{
    /// <summary>
    /// Harmony SSL Hook - Auth.GG Encryption Bypass Tool
    /// 
    /// This tool patches SSL certificate verification in .NET applications
    /// that use auth.gg authentication system, allowing bypass of the
    /// vulnerable AES encryption implementation.
    /// 
    /// Developed by https://github.com/wnelson03, founder of https://keyauth.cc
    /// 
    /// @license MIT
    /// </summary>
    internal class Program
    {
        /// <summary>Path to the target executable</summary>
        private static string AssemblyLoad = string.Empty;

        /// <summary>SSL public key to inject</summary>
        private static readonly string sslKey = "3082010A0282010100ABDA0F3E94C51EDC5DC15E65D0DD98B6AC90EA1F712D1318A081700F5C06B50638456378F97D828D8A7CDFF6907D9A064E1182B62B16B3F4F8D125F8BA1279B42C18D7B14A3356E0F3E0907BBD1B287E33292260E5EBB8B050293AB11E63FEDEFDAFAA6A5DD15EF125832A20A5760BC76B6D10FD3DAAEFDC70924353D699A5C2DD8EF78D1AA5A9F9EFA7EDE7B8DBD893579B2A8EA87FCFF2F50D7E43F75EF8C9D0B01C5D1FB0E9C8E30FFA83AD5BE4A46BD7C707B2B027E5CAA96EF6386617186EFB22ACD2F1231228E75465546DE24C4D54032C3C44594CEC39302FCAD12AE784ACC73FD9E2D43A452A01ABF9ACCE8E124601DD11AFBF43089F636FDB730D270203010001";

        /// <summary>Harmony instance for method patching</summary>
        private static Harmony patchInstance;

        /// <summary>
        /// Generates a cryptographically secure random string
        /// </summary>
        /// <param name="length">Desired length of the string</param>
        /// <returns>Random alphanumeric string</returns>
        public static string GenerateRandomString(int length)
        {
            const string chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
            char[] result = new char[length];

            using (var rng = RandomNumberGenerator.Create())
            {
                byte[] data = new byte[length];
                rng.GetBytes(data);

                for (int i = 0; i < length; i++)
                {
                    result[i] = chars[data[i] % chars.Length];
                }
            }

            return new string(result);
        }

        /// <summary>
        /// Displays application header and banner
        /// </summary>
        private static void DisplayBanner()
        {
            Console.ForegroundColor = ConsoleColor.Cyan;
            Console.WriteLine("╔══════════════════════════════════════════════════════════════╗");
            Console.WriteLine("║              Harmony SSL Hook - Auth.GG Bypass             ║");
            Console.WriteLine("║                    Version 1.0.0                           ║");
            Console.WriteLine("╚══════════════════════════════════════════════════════════════╝");
            Console.ResetColor();
            Console.WriteLine();
        }

        /// <summary>
        /// Displays an error message in red
        /// </summary>
        /// <param name="message">Error message to display</param>
        private static void DisplayError(string message)
        {
            Console.ForegroundColor = ConsoleColor.Red;
            Console.WriteLine($"[ERROR] {message}");
            Console.ResetColor();
        }

        /// <summary>
        /// Displays an info message in green
        /// </summary>
        /// <param name="message">Info message to display</param>
        private static void DisplayInfo(string message)
        {
            Console.ForegroundColor = ConsoleColor.Green;
            Console.WriteLine($"[INFO] {message}");
            Console.ResetColor();
        }

        /// <summary>
        /// Displays a warning message in yellow
        /// </summary>
        /// <param name="message">Warning message to display</param>
        private static void DisplayWarning(string message)
        {
            Console.ForegroundColor = ConsoleColor.Yellow;
            Console.WriteLine($"[WARNING] {message}");
            Console.ResetColor();
        }

        /// <summary>
        /// Validates that the provided file path points to a valid executable
        /// </summary>
        /// <param name="filePath">Path to validate</param>
        /// <returns>True if valid, false otherwise</returns>
        private static bool IsValidExecutable(string filePath)
        {
            if (string.IsNullOrWhiteSpace(filePath))
                return false;

            if (!File.Exists(filePath))
                return false;

            string extension = Path.GetExtension(filePath);
            return extension.Equals(".exe", StringComparison.OrdinalIgnoreCase);
        }

        /// <summary>
        /// Prompts user for a valid executable file path
        /// </summary>
        /// <returns>Valid file path</returns>
        private static string PromptForExecutable()
        {
            while (true)
            {
                Console.WriteLine();
                Console.Write("Please provide a valid executable file [.EXE]: ");
                string input = Console.ReadLine()?.Trim();

                if (IsValidExecutable(input))
                {
                    Console.Clear();
                    return input;
                }

                DisplayError("Invalid file. Please provide a path to a valid .exe file.");
            }
        }

        /// <summary>
        /// Main application entry point
        /// </summary>
        /// <param name="args">Command line arguments</param>
        static void Main(string[] args)
        {
            DisplayBanner();

            // Get executable path from arguments or prompt user
            try
            {
                if (args.Length > 0 && IsValidExecutable(args[0]))
                {
                    AssemblyLoad = args[0];
                    DisplayInfo($"Using executable: {AssemblyLoad}");
                }
                else
                {
                    AssemblyLoad = PromptForExecutable();
                }
            }
            catch (Exception ex)
            {
                DisplayError($"Error processing arguments: {ex.Message}");
                AssemblyLoad = PromptForExecutable();
            }

            // Validate file exists
            if (!File.Exists(AssemblyLoad))
            {
                DisplayError($"File not found: {AssemblyLoad}");
                WaitForExit();
                return;
            }

            // Load and patch the assembly
            try
            {
                DisplayInfo("Loading target assembly...");
                var assembly = Assembly.LoadFile(Path.GetFullPath(AssemblyLoad));

                if (assembly.EntryPoint == null)
                {
                    DisplayError("The target executable does not have a valid entry point.");
                    WaitForExit();
                    return;
                }

                DisplayInfo("Applying SSL certificate patch...");
                var paraminfo = assembly.EntryPoint.GetParameters();
                object[] parameters = new object[paraminfo.Length];

                // Generate unique patch ID
                string patchId = GenerateRandomString(15);
                patchInstance = new Harmony(patchId);

                // Apply patches from this assembly
                patchInstance.PatchAll(Assembly.GetExecutingAssembly());
                DisplayInfo("SSL patch applied successfully!");

                // Invoke the target application
                DisplayInfo("Launching patched application...");
                Console.WriteLine();
                assembly.EntryPoint.Invoke(null, parameters);
            }
            catch (BadImageFormatException ex)
            {
                DisplayError("The target file is not a valid .NET assembly.");
                DisplayError($"Details: {ex.Message}");
            }
            catch (FileLoadException ex)
            {
                DisplayError("Failed to load the target assembly.");
                DisplayError($"Details: {ex.Message}");
            }
            catch (ReflectionTypeLoadException ex)
            {
                DisplayError("Failed to load types from the target assembly.");
                DisplayError("Missing dependencies:");
                foreach (var loaderEx in ex.LoaderExceptions.Where(e => e != null).Distinct())
                {
                    DisplayError($"  - {loaderEx.Message}");
                }
            }
            catch (Exception ex)
            {
                DisplayError($"Could not load {AssemblyLoad}");
                DisplayError($"Exception: {ex.GetType().Name}");
                DisplayError($"Message: {ex.Message}");

                if (ex.InnerException != null)
                {
                    DisplayError($"Inner Exception: {ex.InnerException.Message}");
                }
            }

            WaitForExit();
        }

        /// <summary>
        /// Waits for user input before exiting
        /// </summary>
        private static void WaitForExit()
        {
            Console.WriteLine();
            Console.ForegroundColor = ConsoleColor.Gray;
            Console.WriteLine("Press any key to exit...");
            Console.ResetColor();
            Console.ReadKey(true);
        }

        /// <summary>
        /// Cleans up Harmony patches on application exit
        /// </summary>
        private static void Cleanup()
        {
            try
            {
                patchInstance?.UnpatchSelf();
            }
            catch
            {
                // Ignore cleanup errors
            }
        }

        /// <summary>
        /// Harmony prefix patch for X509Certificate.GetPublicKeyString
        /// Replaces the SSL public key with our known key
        /// </summary>
        [HarmonyPatch(typeof(System.Security.Cryptography.X509Certificates.X509Certificate), nameof(System.Security.Cryptography.X509Certificates.X509Certificate.GetPublicKeyString))]
        class X509CertificatePatch
        {
            /// <summary>
            /// Prefix that replaces the public key string
            /// </summary>
            /// <param name="__result">Return value to modify</param>
            /// <returns>False to skip original method</returns>
            [STAThread]
            static bool Prefix(ref string __result)
            {
                Console.ForegroundColor = ConsoleColor.Yellow;
                Console.WriteLine("[HOOK] SSL certificate public key intercepted and replaced!");
                Console.ResetColor();

                __result = sslKey;
                return false;
            }
        }
    }
}
