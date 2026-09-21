using RestSharp;
namespace RedcapCSharpApiExamples
{
    public static class ImportFile
    {
        public static async Task<RestResponse> ImportFileAsync(string fileName, string filePath)
        {
            try
            {
                // Setup
                var apiToken = Config.ApiToken;
                var uri = new Uri(Config.ApiUrl);
                var _binaryFile = Path.Combine(filePath, fileName);

                // Create Request
                var client = new RestClient();
                var request = new RestRequest(uri, Method.Post);

                request.AddHeader("Content-Type", "multipart/form-data");
                request.AddParameter("token", apiToken);
                request.AddParameter("content", "file");
                request.AddParameter("action", "import");
                request.AddParameter("event", "event_3_arm_1");
                request.AddParameter("field", "file_upload");
                request.AddParameter("record", "1");
                request.AlwaysMultipartFormData = true;
                request.AddFile(fileName, _binaryFile);

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
