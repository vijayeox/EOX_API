using Microsoft.Office.Interop.Excel;
using System;
using System.IO;
using System.Diagnostics;
using System.Text.RegularExpressions;
using Range = Microsoft.Office.Interop.Excel.Range;
using Shape = Microsoft.Office.Interop.Excel.Shape;
using ArrowHeadWebService;
using RestSharp;
using System.Text.Json;
using System.Text.Json.Serialization;
using System.Collections.Generic;
using Microsoft.VisualBasic.CompilerServices;
using Newtonsoft.Json.Linq;
using System.Linq;
using System.Runtime.InteropServices;

namespace ProcessExcel
{
    public class ErrorDetails
    {
        public string worksheet { get; set; }
        public string destinationrange { get; set; }
        public string message { get; set; }
        public string file { get; set; }
        public string folder { get; set; }
        public int errortype { get; set; }

        public static implicit operator List<object>(ErrorDetails v)
        {
            throw new NotImplementedException();
        }
    }

    public class CallbackData
    {
        public string filename { get; set; }
        public string fileId { get; set; }
        public byte status { get; set; }
        public string appId { get; set; }
        public string orgId { get; set; }
        public List<ErrorDetails> errorlist { get; set; }
    }
    class ProcessExcel
    {
        public string baseDirectory { get; set; }
        public bool Errorflag { get; set; }

        private Settings _settings;

        private CallbackData _callback;
        public ProcessExcel(Settings settings)
        {
            _settings = settings;
        }



        public void processFile(string baseFolder, JsonElement jsonbody)
        {
            Errorflag = false;
            this.baseDirectory = baseFolder;
            string carrierfile = "";
            try
            {
                string jsontext = jsonbody.ToString();
                JObject parsedJson = JObject.Parse(jsontext);
                carrierfile = parsedJson["mapping"]["filename"].ToString();            
                _callback = new CallbackData();
                _callback.filename = carrierfile;
                _callback.fileId = parsedJson["fileId"].ToString();
                _callback.appId = parsedJson["appId"].ToString();
                _callback.orgId = parsedJson["orgId"].ToString();
                _callback.errorlist = new List<ErrorDetails>();
                List<string> myvariables = new List<string>();
                string mystr = parsedJson["postURL"].ToString();
                while (mystr.Contains("{"))
                {
                    string variable = mystr.Split('{', '}')[1];
                    string prop = (string)_callback.GetType().GetProperty(variable).GetValue(_callback, null);
                    mystr = mystr.Replace("{" + variable + "}", prop);
                };
                _settings.postURL = mystr;
                LogProcess("Post URL Set to :" + _settings.postURL);
                object data = parsedJson["mapping"]["data"];
                IList<JToken> mappingdata = parsedJson["mapping"]["data"].Children().ToList();
                MapData(carrierfile, mappingdata);
            }
            catch (Exception e)
            {
                PostError(carrierfile, 0,e.StackTrace);
            }

        }

        [System.Runtime.InteropServices.DllImport("user32.dll", CharSet = CharSet.Auto, SetLastError = true)]
        public static extern int GetWindowThreadProcessId(HandleRef handle, out int processId);

        private void MapData(string carrierfile, IList<JToken> mappingdata)
        {
            Application excel = new Application();
            string vFolder = this.baseDirectory + "\\Templates\\";
            string fCarrier = DateTime.Now.ToString("MMddyyyyhhmmss") + "-" + carrierfile;
            File.Copy(vFolder + "\\" + carrierfile, vFolder + "\\" + fCarrier);
            Workbook wbCarrier = excel.Workbooks.Open(vFolder + "\\" + fCarrier);
            
            HandleRef hwnd = new HandleRef(excel, (IntPtr)excel.Hwnd);
            int pid;
            GetWindowThreadProcessId(hwnd, out pid);
            wbCarrier.Activate();

            try
            {
                foreach(JToken mapdata in mappingdata)
                {
                    Copycells(wbCarrier, mapdata);
                }
                wbCarrier.Save();
                wbCarrier.Close();
                PostFile(vFolder, fCarrier);
                File.Move(vFolder + "\\" + fCarrier, this.baseDirectory + "\\Processed\\" + fCarrier);
            }
            catch (Exception e)
            {
                if (wbCarrier != null)
                {
               //     Logerror("Error:" + e, wbCarrier.Name);
                    wbCarrier.Close();
                }

                PostError(carrierfile, 1,e.StackTrace);
            }
            finally
            {
                excel.Quit();
                System.Runtime.InteropServices.Marshal.ReleaseComObject(excel);
                Process excelProcs = Process.GetProcessById(pid);
                excelProcs.Kill();
            }
        }

        private void Copycells(Workbook wbCarrier, JToken mapdata)
        {
            string vVal;
            string macro="";
            string vDestSheet = mapdata["pageName"].ToString();
            string vDestRange = mapdata["cell"].ToString();
            string vSpecial = mapdata["type"].ToString();
            bool vStatic = false;
            if (mapdata["offset"] != null)
            {
                if (mapdata["offset"].ToString()!="")
                {
                    vDestRange = FindByNameAndOffset(wbCarrier, vDestSheet, vDestRange, mapdata["offset"].ToString());
                }
            }
            if (mapdata["macro"] != null)
            {
                macro = mapdata["macro"].ToString();
            } 
            try
            {
                switch (vSpecial)
                {
                    case "DataGridStatic":
                        vStatic = true;
                        JToken value = mapdata["value"];
                        List<List<string>> values = mapdata["value"].ToObject<List<List<string>>>();
                        CopyTable(wbCarrier, vDestSheet, vDestRange, values, macro,vStatic);
                        break;
                    case "DataGrid":
                        JToken value2 = mapdata["value"];
                        List<List<string>> values2 = mapdata["value"].ToObject<List<List<string>>>();
                        CopyTable(wbCarrier, vDestSheet, vDestRange,values2,macro,vStatic);
                        break;
                    case "Checkbox":
                        vVal = mapdata["value"].ToString();
                        if (vVal == "1" || vVal.ToUpper() == "YES" || vVal.ToUpper() == "TRUE")
                        {
                                Microsoft.Office.Interop.Excel.Shape shp = (wbCarrier.Worksheets[vDestSheet] as Worksheet).Shapes.Item(vDestRange);
                                shp.ControlFormat.Value = 1;
                            }
                        break;
                    case "Macro":
                        Worksheet ws = (wbCarrier.Worksheets[vDestSheet] as Worksheet);
                        ws.Activate();
                        wbCarrier.Application.Run(macro);
                    default:
                        vVal = mapdata["value"].ToString();
                        if (vVal!="") { 
                            (wbCarrier.Worksheets[vDestSheet] as Worksheet).Range[vDestRange].Value = vVal;
                        }
                        //string val = wbCarrier.Worksheets[vSourceSheet].Range[vSourceRange].Value;
                        break;
                }
            }
            catch (Exception e)
            {
                ErrorDetails errordetail = new ErrorDetails();
                errordetail.file = wbCarrier.Name;
                errordetail.folder = this.baseDirectory;
                errordetail.worksheet = vDestSheet;
                errordetail.message = e.ToString();
                errordetail.destinationrange = vDestRange;
                errordetail.errortype = 2;
                _callback.errorlist.Add(errordetail);
            }

        }

        public void CopyTable(Workbook wbCarrier, string vDestSheet, string vDestRange,  List<List<string>> values, string macro,bool vStatic)
        {
            try
            {
                int rowindex = (wbCarrier.Worksheets[vDestSheet] as Worksheet).Range[vDestRange].Row;
                int colindex = (wbCarrier.Worksheets[vDestSheet] as Worksheet).Range[vDestRange].Column;

                int f = 0;
                foreach (List<string> row in values)
                {
                    if (macro != "" && vStatic==false)
                    {
                        if(row.Count() > 0 && f!=0)
                        {
                            Worksheet ws = (wbCarrier.Worksheets[vDestSheet] as Worksheet);
                            ws.Activate();
                            wbCarrier.Application.Run(macro);
                        }
                    }
                    int i = colindex;
                    foreach (string value in row)
                    {
                        if (value != "")
                        {
                            (wbCarrier.Worksheets[vDestSheet] as Worksheet).Cells[rowindex, i] = value;
                        }

                        i++;
                    }
                    if (vStatic==false)
                    {
                        rowindex++;
                    } else
                    {
                        if (macro != "")
                        {
                            Worksheet ws = (wbCarrier.Worksheets[vDestSheet] as Worksheet);
                            ws.Activate();
                            wbCarrier.Application.Run(macro);
                        }
                    }
                    f++;
                }
            } catch 
            {
                throw;
            }
        }

        public void Logerror(string errormsg, string fname)
        {
            fname = fname.Replace(".", "-");
            using (StreamWriter errorfile = File.AppendText(this.baseDirectory + @"\Errors\" + fname + "-error.txt"))
            {
                errorfile.WriteLine(errormsg);
                errorfile.Close();
            }
            Errorflag = true;
        }

        public void LogProcess(string message = "")
        {
            try
            {
                if (_settings.logging == 1)
                {
                    using (StreamWriter logFile = File.AppendText(this.baseDirectory + @"\processed\logs.txt"))
                    {
                        logFile.WriteLine(DateTime.Now.ToString("H:m") + " -> " + message);
                        logFile.Close();
                    }
                }
            }
            catch (Exception e)
            {
                throw new System.ArgumentException("Error in logProcess:" + e.Message);
            }
        }



        private void PostFile(string folder, string fileName)
        {
            try
            {
                var client = new RestClient(_settings.postURL);
                client.Timeout = -1;
                var request = new RestRequest(Method.POST);
                request.AddFile("file", folder + "\\" + fileName);
                // request.AddHeader("Authorization", "Bearer " + _settings.token);
                request.AlwaysMultipartFormData = true;
                _callback.status = 1;
                //  var postCallbackData = JsonSerializer.Serialize(_callback);
                //  request.AddParameter("data", postCallbackData, ParameterType.RequestBody);
                request.AddParameter("orgId", _callback.orgId);
                request.AddParameter("filename", _callback.filename);
                request.AddParameter("fileId", _callback.fileId);
                request.AddParameter("status", _callback.status);
                request.AddParameter("errorlist", JsonSerializer.Serialize(_callback.errorlist));
                LogProcess("Parameters:"+ JsonSerializer.Serialize(request.Parameters));
                string fname = fileName.Replace(".", "-");
                IRestResponse response = client.Execute(request);
                LogProcess("Sending Post:" + fileName + " to " + _settings.postURL);
                LogProcess("Response:" + response.Content);
            } catch (Exception e) {
                Logerror(e.Message, fileName);
            }
        }

        private void PostError(string fileName, int errortype, string message)
        {
            try
            {
                var client = new RestClient(_settings.postURL);
                client.Timeout = -1;
                var request = new RestRequest(Method.POST);
                ErrorDetails errordetail = new ErrorDetails()
                {
                    file = fileName,
                    folder = this.baseDirectory,
                    destinationrange = "",
                    message = message,
                    worksheet = "",
                    errortype = errortype
                };
                _callback.status = 0;
                _callback.errorlist = new List<ErrorDetails> { errordetail };
                //          var postCallbackData = JsonSerializer.Serialize(_callback);
                //          LogProcess(postCallbackData);
                //          request.AddParameter("data", postCallbackData, ParameterType.RequestBody);
                request.AddParameter("orgId", _callback.orgId);
                request.AddParameter("filename", _callback.filename);
                request.AddParameter("fileId", _callback.fileId);
                request.AddParameter("status", _callback.status);
                request.AddParameter("errorlist", JsonSerializer.Serialize(_callback.errorlist));
                LogProcess("Parameters:" + JsonSerializer.Serialize(request.Parameters));
                IRestResponse response = client.Execute(request);
                LogProcess("Sending Error Post:" + fileName + " to " + _settings.postURL);
            }
            catch (Exception e)
            {
                Logerror(e.Message, fileName);
            }
        }

        public string FindByNameAndOffset(Workbook wbCarrier, string vDestSheet,string text, string offset)
        {
            string[] offsetarry = offset.Split(",");
            int offsetrow = int.Parse(offsetarry[0]);
            int offsetcol = int.Parse(offsetarry[1]);
            Worksheet sheet = (Worksheet)wbCarrier.Worksheets[vDestSheet];
            Range range = sheet.Cells;
            var result = range.Find(text, LookAt: XlLookAt.xlPart);
            int rowindex = result.Row;
            int colindex = result.Column;
            rowindex = rowindex + offsetrow;
            colindex = colindex + offsetcol;
            var address = (sheet.Cells[rowindex, colindex] as Range).Address;
            return address;
        }




    }
}