using System;

namespace OmSkimmer
{
    class Program
    {
        static void Main(string[] args)
        {
            using (HtmlParser parser = new HtmlParser())
            {
                parser.ParseOmData();
            }

            Console.WriteLine("<Enter> to exit.");
            Console.ReadLine();
        }
    }
}
