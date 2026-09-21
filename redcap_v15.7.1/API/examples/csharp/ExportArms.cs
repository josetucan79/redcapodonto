using RestSharp;
namespace RedcapCSharpApiExamples
{
    public static class ExportArms
    {
        public static async Task<RestResponse> ExportArmsAsync(string[] arms)
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
                request.AddParameter("content", "arm");
                request.AddParameter("format", "json");
                request.AddParameter("returnFormat", "json");
                // Optional
                if (arms?.Length > 0)
                {
                    for (var i = 0; i < arms.Length; i++)
                    {
                        request.AddParameter($"arms[{i}]", arms[i]);
                    }
                }
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
