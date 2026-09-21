using RestSharp;
namespace RedcapCSharpApiExamples
{
    public static class ExportSurveyParticipants
    {
        public static async Task<RestResponse> ExportSurveyParticipantsAsync()
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
                request.AddParameter("content", "participantList");
                request.AddParameter("format", "json");
                request.AddParameter("instrument", "the_instrument_name");
                request.AddParameter("event", "event_1_arm_1");
                request.AddParameter("returnFormat", "json");

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
