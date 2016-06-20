using System;
using System.IO;

namespace OmSkimmer
{
    public class Logger : IDisposable
    {
        #region Members

        private Boolean disposed = false;

        private String outputRoot;
        private String OutputRoot
        {
            get
            {
                if (String.IsNullOrEmpty(this.outputRoot))
                {
                    const String debugFolder = @"\OmSkimmer\bin\Debug\";
                    this.outputRoot = AppDomain.CurrentDomain.BaseDirectory;
                    if (this.outputRoot.EndsWith(debugFolder, StringComparison.OrdinalIgnoreCase))
                    {
                        this.outputRoot = this.outputRoot.Substring(0,
                            this.outputRoot.IndexOf(debugFolder, StringComparison.OrdinalIgnoreCase));
                    }
                    this.outputRoot = Path.Combine(this.outputRoot, DateTime.Today.ToString("yyyy_MM_dd"));

                    if (!Directory.Exists(this.outputRoot))
                    {
                        Directory.CreateDirectory(this.outputRoot);
                    }
                }

                return this.outputRoot;
            }
        }

        private String logFileName;
        private String LogFileName
        {
            get
            {
                if (String.IsNullOrEmpty(this.logFileName))
                {
                    String logFolder = Path.Combine(this.OutputRoot, "Logs");
                    if (!Directory.Exists(logFolder))
                    {
                        Directory.CreateDirectory(logFolder);
                    }

                    this.logFileName = Path.Combine(logFolder,
                        String.Format("log_{0}.txt", DateTime.Now.ToString("HH_mm_ss")));
                }

                return this.logFileName;
            }
        }
        
        private StreamWriter logStream;
        private StreamWriter LogStream
        {
            get
            {
                return this.logStream ?? (this.logStream = File.AppendText(this.LogFileName));
            }
        }

        private String CsvFileName
        {
            get
            {
                return Path.Combine(this.OutputRoot, "OmPriceList.csv");
            }
        }

        private StreamWriter csvStream;
        private StreamWriter CsvStream
        {
            get
            {
                return this.csvStream ?? (this.csvStream = File.AppendText(this.CsvFileName));
            }
        }

        #endregion Members

        #region Constructors

        #endregion Constructors

        #region Methods

        public void WriteToConsole(String message, params Object[] formatArgs)
        {
            Console.Write(formatArgs.Length > 0 ? String.Format(message, formatArgs) : message);
        }

        public void WriteLineToConsole(String message, params Object[] formatArgs)
        {
            Console.WriteLine(formatArgs.Length > 0 ? String.Format(message, formatArgs) : message);
        }

        public void OverwriteLineToConsole(String message, params Object[] formatArgs)
        {
            Console.Write("\r{0}", formatArgs.Length > 0 ? String.Format(message, formatArgs) : message);
        }

        public void WriteToLogFile(String message, params Object[] formatArgs)
        {
            this.LogStream.Write(formatArgs.Length > 0 ? String.Format(message, formatArgs) : message);
        }

        public void WriteLineToLogFile(String message, params Object[] formatArgs)
        {
            this.LogStream.WriteLine(formatArgs.Length > 0 ? String.Format(message, formatArgs) : message);
        }

        public void WriteToConsoleAndLogFile(String message, params Object[] formatArgs)
        {
            this.WriteToConsole(message, formatArgs);
            this.WriteToLogFile(message, formatArgs);
        }

        public void WriteLineToConsoleAndLogFile(String message, params Object[] formatArgs)
        {
            this.WriteLineToConsole(message, formatArgs);
            this.WriteLineToLogFile(message, formatArgs);
        }

        public void BlankLineInConsole()
        {
            Console.WriteLine();
        }

        public void BlankLineInLogFile()
        {
            this.LogStream.WriteLine();
        }

        public void BlankLineInConsoleAndLogFile()
        {
            this.BlankLineInConsole();
            this.BlankLineInLogFile();
        }

        public void WriteProductToCsv(Product product)
        {
            this.CsvStream.WriteLine(product.Csv);
        }
        
        #endregion Methods

        #region IDisposable members

        private void Dispose(bool disposing)
        {
            if (!disposed)
            {
                if (disposing)
                {
                    if (this.LogStream != null)
                    {
                        this.LogStream.Close();
                        this.LogStream.Dispose();
                    }
                    if (this.CsvStream != null)
                    {
                        this.CsvStream.Close();
                        this.CsvStream.Dispose();
                    }
                }

                disposed = true;
            }
        }

        public void Dispose()
        {
            this.Dispose(true);
        }
        
        #endregion IDisposable members

    }
}
