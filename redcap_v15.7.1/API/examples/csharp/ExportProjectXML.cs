using RestSharp;
namespace RedcapCSharpApiExamples
{
    public static class ExportProjectXML
    {
        public static async Task<RestResponse> ExportProjectXMLAsync()
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
                request.AddParameter("content", "project_xml");
                request.AddParameter("returnMetadataOnly", "false");
                request.AddParameter("exportSurveyFields", "false");
                request.AddParameter("exportSurveyFields", "false");
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
