using RestSharp;
namespace RedcapCSharpApiExamples
{
    public static class RenameRecord
    {  
        public static async Task<RestResponse> RenameRecordAsync()
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
                request.AddParameter("content", "record");
                request.AddParameter("action", "rename");
                request.AddParameter("record", "record_1");
                request.AddParameter("new_record_name", "1");
                request.AddParameter("arm", "1");
                request.AddParameter("returnFormat","json");

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
