using RestSharp;

using System.Text.Json.Nodes;
namespace RedcapCSharpApiExamples
{
    public static class ImportArms
    {
        public static async Task<RestResponse> ImportArmsAsync()
        {
            try
            {
                // Setup
                var apiToken = Config.ApiToken;
                var uri = new Uri(Config.ApiUrl);
                var arms = new JsonObject
                {
                    {"arm_num", "3"},
                    {"name", "Arm 3"}
                };
                var data = new JsonArray();
                data.Add(arms);

                // Create Request
                var client = new RestClient();
                var request = new RestRequest(uri, Method.Post);
                request.AddHeader("Content-Type", "application/x-www-form-urlencoded");
                request.AddParameter("token", apiToken);
                request.AddParameter("content", "arm");
                request.AddParameter("action", "import");
                request.AddParameter("format", "json");
                request.AddParameter("override", "0");
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
