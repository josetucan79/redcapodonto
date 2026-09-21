using RestSharp;

using System.Text.Json.Nodes;
namespace RedcapCSharpApiExamples
{
    public static class ImportUserDagMaps
    {
        public static async Task<RestResponse> ImportUserDagMapsAsync()
        {
            try
            {
                // Setup
                var apiToken = Config.ApiToken;
                var uri = new Uri(Config.ApiUrl);
                var record = new JsonObject
                {
                    {"username", "test_user"},
                    {"redcap_data_access_group", "api_testing_group"}
                };
                var data = new JsonArray();
                data.Add(record);

                // Create Request
                var client = new RestClient();
                var request = new RestRequest(uri, Method.Post);
                request.AddHeader("Content-Type", "application/x-www-form-urlencoded");
                request.AddParameter("token", apiToken);
                request.AddParameter("content", "userDagMapping");
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
