using RestSharp;

using System.Text.Json.Nodes;
namespace RedcapCSharpApiExamples
{
    public static class ImportDags
    {
        public static async Task<RestResponse> ImportDagsAsync()
        {
            try
            {
                // Setup
                var apiToken = Config.ApiToken;
                var uri = new Uri(Config.ApiUrl);
                var dags = new JsonObject
                {
                    {"data_access_group_name", "API GROUP"},
                    {"unique_group_name", "api_group"}
                };
                var data = new JsonArray();
                data.Add(dags);

                // Create Request
                var client = new RestClient();
                var request = new RestRequest(uri, Method.Post);
                request.AddHeader("Content-Type", "application/x-www-form-urlencoded");
                request.AddParameter("token", apiToken);
                request.AddParameter("content", "dag");
                request.AddParameter("action", "import");
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
