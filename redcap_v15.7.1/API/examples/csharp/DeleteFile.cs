using RestSharp;
namespace RedcapCSharpApiExamples
{
    public static class DeleteFile
    {
        public static async Task<RestResponse> DeleteFileAsync(string field, string theEvent, string record)
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
                request.AddParameter("content", "file");
                request.AddParameter("action", "delete");
                request.AddParameter("field", field);
                request.AddParameter("event", theEvent);
                request.AddParameter("record", record);
                request.AddParameter("returnFormat", "json");

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
