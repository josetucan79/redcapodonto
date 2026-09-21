// Purpose: Main entry point for the RedcapCSharpApiExamples project.
namespace RedcapCSharpApiExamples
{
    public class Program
    {
        static async Task Main()
        {
            var result = await ExportRecords.ExportRecordsAsync();
            Console.WriteLine($"Status Code: {result.StatusCode}");
            Console.WriteLine($"Content: {result.Content}");
            Console.ReadLine();
        }
    }
}
