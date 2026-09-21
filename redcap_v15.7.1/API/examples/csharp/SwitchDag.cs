using RestSharp;
namespace RedcapCSharpApiExamples
{
    public static class SwitchDag
    {  
        public async static Task<RestResponse> SwitchDagAsync()
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
                request.AddParameter("content", "dag");
                request.AddParameter("action", "switch");
                request.AddParameter("dag", "unique_dag_name");

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
