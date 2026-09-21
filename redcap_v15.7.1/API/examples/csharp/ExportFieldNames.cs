using RestSharp;
namespace RedcapCSharpApiExamples
{
    public static class ExportFieldNames
    {
        public static async Task<RestResponse> ExportFieldNamesAsync(string[] fieldNames)
        {
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
                request.AddParameter("content", "exportFieldNames");
                request.AddParameter("format", "json");
                request.AddParameter("returnFormat", "json");
                if (fieldNames.Count() > 0)
                {
                    foreach(var field in fieldNames)
                    {
                        request.AddParameter("field", field);
                    }
                }
                // Execute Request
                var response = await client.ExecuteAsync(request);
                return response;
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                throw;
            }
        }
    }
}
