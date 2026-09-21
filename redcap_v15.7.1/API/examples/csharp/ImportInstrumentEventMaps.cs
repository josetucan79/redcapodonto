using RestSharp;

using System.Text.Json.Nodes;
namespace RedcapCSharpApiExamples
{
    public static class ImportInstrumentEventMaps
    {
        public static async Task<RestResponse> ImportInstrumentEventMapsAsync()
        {
            try
            {
                // Setup
                var apiToken = Config.ApiToken;
                var uri = new Uri(Config.ApiUrl);
                var form1 = new JsonArray();
                form1.Add("instrument_1");
                form1.Add("instrument_2");

                var form2 = new JsonArray();
                form2.Add("instrument_1");

                var event1 = new JsonObject();

                event1.Add("unique_event_name", "event_1_arm_1");
                event1.Add("form", form1);
                var event2 = new JsonObject();
                event2.Add("unique_event_name", "event_2_arm_1");
                event2.Add("form", form2);

                var events = new JsonArray();
                events.Add(event1);
                events.Add(event2);

                var arm = new JsonObject();
                arm.Add("number", "1");
                arm.Add("event", events);

                var data = new JsonObject();
                data.Add("arm", arm);


                // Create Request
                var client = new RestClient();
                var request = new RestRequest(uri, Method.Post);
                request.AddHeader("Content-Type", "application/x-www-form-urlencoded");
                request.AddParameter("token", apiToken);
                request.AddParameter("content", "arm");
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
