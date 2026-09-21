using RestSharp;
namespace RedcapCSharpApiExamples
{
    public static class DeleteArms
    {
        public static async Task<RestResponse> DeleteArmsAsync(string[] arms)
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
                request.AddParameter("content", "dag");
                request.AddParameter("action", "delete");
                request.AddParameter("format", "json");

                for (var i = 0; i < arms.Length; i++)
                {
                    request.AddParameter($"arms[{i}]", arms[i]);
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
