using RestSharp;
namespace RedcapCSharpApiExamples
{
    public static class ExportInstrumentsPDF
    {
        public static async Task<RestResponse> ExportInstrumentsPDFAsync(string record, string eventName, string instrument)
        {
            RestResponse response = new RestResponse();
            try
            {
                // Setup
                var apiToken = Config.ApiToken;
                var uri = new Uri(Config.ApiUrl);

                // Create Request
                var client = new RestClient();
                var request = new RestRequest(uri, Method.Post);
                request.AddHeader("Content-Type", "application/x-www-form-urlencoded");
                request.AddParameter("token", apiToken);
                request.AddParameter("content", "pdf");
                request.AddParameter("instrument", instrument);
                request.AddParameter("event", eventName);
                request.AddParameter("record", record);
                request.AddParameter("returnFormat", "json");

                // Execute Request
                response = await client.ExecuteAsync(request);

                if (response.IsSuccessStatusCode) {
                    if (response.Content?.Length > 0)
                    {
                        
                        // Display Response
                        Console.WriteLine("Saving pdf file to MyDocuments");

                        // Set a variable to the Documents path.
                        string docPath = Environment.GetFolderPath(Environment.SpecialFolder.MyDocuments);

                        // Write the content to a new file named "MyPdf.pdf" into the "My Documents" folder.
                        var path = Path.Combine(docPath, "MyPdf.pdf");

                        FileStream fs = new FileStream(path, FileMode.Create);
                        if (response?.RawBytes?.Length > 0)
                        {
                            fs.Write(response.RawBytes, 0, response.RawBytes.Length);
                            fs.Close();
                        }
                        else
                        {
                            Console.WriteLine("No content to write.");
                        }
                    }
                    else
                    {
                        Console.WriteLine("No content returned.");
                    }
                }
                return response!;
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                return response!;
            }
        }   
    }
}
