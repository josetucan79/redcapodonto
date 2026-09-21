using RestSharp;
namespace RedcapCSharpApiExamples
{
    public static class DeleteRecords
    {
        public static async Task<RestResponse> DeleteRecordsAsync(string[] records, string[] arm)
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
                request.AddParameter("content", "record");
                request.AddParameter("action", "delete");

                for (var i = 0; i < records.Length; i++)
                {
                    request.AddParameter($"records[{i}]", records[i]);
                }

                // Optional
                request.AddParameter("arm", arm?.ToString());

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
