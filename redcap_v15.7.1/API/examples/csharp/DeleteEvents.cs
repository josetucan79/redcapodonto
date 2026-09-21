using RestSharp;
namespace RedcapCSharpApiExamples
{
    public static class DeleteEvents
    {

        public static async Task<RestResponse> DeleteEventsAsync(string[] events)
        {
            try
            {
                // Setup
                var apiToken = Config.ApiToken;
                var uri = new Uri(Config.ApiUrl);

                // Create Request
                var client = new RestClient();
                var request = new RestRequest(uri, Method.Post);
                request.AddParameter("token", apiToken);
                request.AddParameter("content", "event");
                request.AddParameter("action", "delete");
                request.AddParameter("format", "json");

                for (var i = 0; i < events.Length; i++)
                {
                    request.AddParameter($"events[{i}]", events[i]);
                }

                // Execute Request
                var response = await client.ExecuteAsync(request);
                return response;
            }
            catch (Exception e)
            {
                Console.WriteLine(e);
                throw;
            }
        }
    }
}
