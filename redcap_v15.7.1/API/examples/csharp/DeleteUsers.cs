using RestSharp;
namespace RedcapCSharpApiExamples
{
    public static class DeleteUsers
    {
        public static async Task<RestResponse> DeleteUsersAsync(string[] users)
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
                request.AddParameter("content", "user");
                request.AddParameter("action", "delete");
                request.AddParameter("format", "json");

                for (var i = 0; i < users.Length; i++)
                {
                    request.AddParameter($"users[{i}]", users[i]);
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
