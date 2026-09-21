using RestSharp;

using System.Text.Json.Nodes;
namespace RedcapCSharpApiExamples
{
    public static class ImportUserRoles
    {
        public static async Task<RestResponse> ImportUserRolesAsync()
        {
            try
            {
                // Setup
                var apiToken = Config.ApiToken;
                var uri = new Uri(Config.ApiUrl);
                var record = new JsonObject
                {
                    {"role_label", "Project Manager"},
                    {"api_import", "1" },
                    {"user_rights", "1" },
                    {"design", "1" }
                };
                var data = new JsonArray();
                data.Add(record);

                // Create Request
                var client = new RestClient();
                var request = new RestRequest(uri, Method.Post);
                request.AddHeader("Content-Type", "application/x-www-form-urlencoded");
                request.AddParameter("token", apiToken);
                request.AddParameter("content", "userRole");
                request.AddParameter("format", "json");
                request.AddParameter("data", data.ToJsonString());

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
