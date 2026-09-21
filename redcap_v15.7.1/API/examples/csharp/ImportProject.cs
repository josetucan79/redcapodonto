using RestSharp;

using System.Text.Json.Nodes;
namespace RedcapCSharpApiExamples
{
    public static class ImportProject
    {
        public static async Task<RestResponse> ImportProjectAsync()
        {
            try
            {
                // Setup (Using SUPER TOKEN)
                var apiToken = Config.ApiSuperToken;
                var uri = new Uri(Config.ApiUrl);
                var project = new JsonObject
                {
                    {"project_title", "API Project"},
                    {"purpose", "0"},
                    {"purpose_other", ""},
                    {"project_notes", "some notes"}
                };
                var data = new JsonArray();
                data.Add(project);

                // Create Request
                var client = new RestClient();
                var request = new RestRequest(uri, Method.Post);
                request.AddHeader("Content-Type", "application/x-www-form-urlencoded");
                request.AddParameter("token", apiToken);
                request.AddParameter("content", "project");
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
