using RestSharp;

using System.Text.Json.Nodes;
namespace RedcapCSharpApiExamples
{
    public static class ImportRecords {         
        public static async Task<RestResponse> ImportRecordsAsync()
        {
            try
            {
                // Setup
                var apiToken = Config.ApiToken;
                var uri = new Uri(Config.ApiUrl);
                var records = new JsonObject
                {
                    {"record_id", "1"},
                    {"redcap_event_name", "event_3_arm_1"},
                    {"first_name", "Mike"},
                    {"last_name", "Jones" }
                };
                var data = new JsonArray();
                data.Add(records);

                // Create Request
                var client = new RestClient();
                var request = new RestRequest(uri, Method.Post);
                request.AddHeader("Content-Type", "application/x-www-form-urlencoded");
                request.AddParameter("token", apiToken);
                request.AddParameter("content", "record");
                request.AddParameter("format", "json");
                request.AddParameter("type", "flat");
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
