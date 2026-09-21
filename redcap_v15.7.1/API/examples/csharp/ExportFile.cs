using RestSharp;
namespace RedcapCSharpApiExamples
{
    public static class ExportFile
    {
        public static async Task<RestResponse> ExportFileAsync(string recordId, string field, string eventName)
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
                request.AddParameter("content", "file");
                request.AddParameter("action", "export");
                request.AddParameter("record", recordId);
                request.AddParameter("field", field);
                request.AddParameter("event", eventName);
                request.AddParameter("returnFormat", "json");

                // Execute Request
                response = await client.ExecuteAsync(request);
                if (response.IsSuccessStatusCode)
                {
                    if (response.Content?.Length > 0)
                    {
                        Console.WriteLine("Writing to file..");
                        // Set a variable to the Documents path.
                        string docPath = Environment.GetFolderPath(Environment.SpecialFolder.MyDocuments);

                        // Write the content to a new file named "thefile.raw" into the "My Documents" folder.
                        var path = Path.Combine(docPath, "thefile.raw");

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
