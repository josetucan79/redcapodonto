using RestSharp;
namespace RedcapCSharpApiExamples
{
    public static class DeleteUserRoles
    {
        public static async Task<RestResponse> DeleteUserRolesAsync(string[] userRoles)
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
                request.AddParameter("content", "userRole");
                request.AddParameter("action", "delete");

                // Required
                for (var i = 0; i < userRoles.Length; i++)
                {
                    request.AddParameter($"roles[{i}]", userRoles[i]);
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
