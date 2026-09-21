using RestSharp;

using System.Text.Json.Nodes;
namespace RedcapCSharpApiExamples
{
    public static class ImportEvents
    {
        public static async Task<RestResponse> ImportEventsAsync()
        {
            try
            {
                // Setup
                var apiToken = Config.ApiToken;
                var uri = new Uri(Config.ApiUrl);
                var events = new JsonObject
                {
                    {"event_name", "Event 3"},
                    {"arm_num", "1"},
                    {"day_offset", "0"},
                    {"offset_min", "0"},
                    {"offset_max", "0"},
                    {"unique_event_name", "event_1_arm_1"}
                };
                var data = new JsonArray();
                data.Add(events);

                // Create Request
                var client = new RestClient();
                var request = new RestRequest(uri, Method.Post);
                request.AddHeader("Content-Type", "application/x-www-form-urlencoded");
                request.AddParameter("token", apiToken);
                request.AddParameter("content", "event");
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
