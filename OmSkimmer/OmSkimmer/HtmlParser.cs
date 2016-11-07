using HtmlAgilityPack;
using Newtonsoft.Json;
using System;
using System.Collections.Generic;
using System.Collections.Specialized;
using System.Globalization;
using System.Linq;
using System.Net;

namespace OmSkimmer
{
    public class HtmlParser : IDisposable
    {
        #region Members

        private Boolean disposed = false;
        private readonly Int32 numberToProcess = 0;

        private const String OmSiteRoot = @"http://www.omfoods.com/organic-bulk-food";
        private const String CategoryClassName = @"SideCategoryListFlyout";
        private const String ProductMainDivClassName = @"ProductMain";
        private const String SkuSpanItemprop = @"sku";
        private const String NameH1Itemprop = @"name";
        private const String ProductClassName = @"ProductDetails";
        private const String SizeDivClassName = @"productOptionViewRadio";
        private const String PriceClassName = @"PriceRow";
        private const String PriceMetaItemprop = @"price";
        private const String ProductDescriptionClassName = @"ProductDescriptionContainer";
        private const String OutOfStockHack = @"$(""#ProductDetails"").updateProductDetails({""purchasable"":false,""purchasingMessage"":""Out of Stock""";

        private readonly Logger logger;

        #endregion Members

        #region Constructors

        /// <summary>
        /// Constructor accepting processing options and number of products to process
        /// </summary>
        /// <param name="outputOptions">Output options</param>
        /// <param name="processThisMany">Number of products to process (useful for testing purposes)</param>
        public HtmlParser(Shared.Options outputOptions, Int32 processThisMany = 0)
        {
            this.numberToProcess = processThisMany;
            this.logger = new Logger(outputOptions);
        }
        
        #endregion Constructors

        #region ParseOmData

        /// <summary>
        /// Main method that parses the whole damn thing
        /// </summary>
        public void ParseOmData()
        {
            try
            {
                this.logger.WriteToConsoleAndLogFile("Reading main page ... ");

                HtmlDocument mainPage = this.ReadPage(OmSiteRoot);
                if (mainPage == null)
                {
                    return;
                }

                // Get the category nodes - this should be a single div with a class attribute containing CategoryClassName
                List<HtmlNode> categoryNodeList = this.GetDescendantListByClassName(mainPage.DocumentNode, "div", CategoryClassName);

                this.logger.WriteToConsoleAndLogFile("Found {0} category node(s) ", categoryNodeList.Count);
                if (!categoryNodeList.Any())   // If we have no categories, there's nothing we can do ...
                {
                    this.logger.BlankLineInConsoleAndLogFile();
                    return;
                }

                // Parse out the URLs from the anchor tags in the category div
                // This will be a list of string Tuples, where Item1 is the inner text, and Item2 is the URL
                List<Tuple<String, String>> categoryTupleList = this.GetUrlAndNameFromAnchors(categoryNodeList);

                this.logger.WriteLineToConsoleAndLogFile(" with {0} category links", categoryTupleList.Count);
                if (!categoryTupleList.Any())     // If we have no URLs that we could parse out of the categories, there's nothing we can do ...
                {
                    this.logger.WriteLineToConsoleAndLogFile("Unable to parse any URLs out of the category node(s).");
                    return;
                }

                // Log the category URLs in the log file. No need to write details to console.
                this.logger.BlankLineInLogFile();
                this.logger.WriteLineToLogFile("Category URLs:");
                foreach (Tuple<String, String> categoryTuple in categoryTupleList)
                {
                    this.logger.WriteLineToLogFile("{0}: {1}", categoryTuple.Item1, categoryTuple.Item2);
                }
                this.logger.BlankLineInLogFile();

                // Process each category page
                Int32 currentCategoryIndex = 0;
                Int32 productsProcessed = 0;
                List<Product> productList = new List<Product>();
                foreach (Tuple<String, String> categoryTuple in categoryTupleList)
                {
                    if (this.numberToProcess > 0 && productsProcessed >= this.numberToProcess)
                    {
                        break;
                    }
                    
                    this.logger.BlankLineInConsole();
                    this.logger.WriteToConsoleAndLogFile("Reading {0} page ({1} of {2}) ... ", categoryTuple.Item1, ++currentCategoryIndex, categoryTupleList.Count);

                    HtmlDocument categoryPage = this.ReadPage(categoryTuple.Item2);
                    if (categoryPage == null)   // Skip failed category URLs
                    {
                        continue;
                    }

                    // Get the product detail nodes. There should be a bunch of them. These are divs with class attribute containing ProductClassName
                    List<HtmlNode> productNodeList = this.GetDescendantListByClassName(categoryPage.DocumentNode, "div", ProductClassName);

                    this.logger.WriteLineToConsoleAndLogFile("Found {0} product node(s) for {1}", productNodeList.Count, categoryTuple.Item1);
                    if (!productNodeList.Any())   // There really should be product divs on each category page - but if we can't get them, there's nothing to do ...
                    {
                        continue;
                    }

                    // Parse out the URLs from the anchor tags in all the product divs. There should be one anchor tag per product div.
                    List<Tuple<String, String>> productTupleList = this.GetUrlAndNameFromAnchors(productNodeList);                    
                    if (!productTupleList.Any())   // We need to have URLs for product pages to continue
                    {
                        this.logger.WriteLineToConsoleAndLogFile("Unable to parse any URLs out for the {0} category.", categoryTuple.Item1);
                        continue;
                    }

                    // Now we have to hit each product page
                    Int32 currentProductIndex = 0;
                    foreach (Tuple<String, String> productTuple in productTupleList)
                    {
                        this.logger.OverwriteLineToConsole("Processing product {0} of {1} for {2}.",
                            ++currentProductIndex, productTupleList.Count, categoryTuple.Item1);

                        // If this URL was already processed for another category, skip it
                        Product processedProduct = productList.FirstOrDefault(p => p.OmUrl == productTuple.Item2);
                        if (processedProduct != null)
                        {
                            this.logger.WriteLineToLogFile(
                                "SKIPPING {0} ({1}) - THIS WAS ALREADY PROCESSED FOR CATEGORY {2} ... ",
                                productTuple.Item1, productTuple.Item2, processedProduct.Category);
                            continue;
                        }
                        
                        this.logger.WriteToLogFile("Reading {0} page ... ", productTuple.Item1);
                        HtmlDocument productPage = this.ReadPage(productTuple.Item2, true, false);
                        if (productPage == null)    // If we couldn't read the product page, move on, there's nothing we can do
                        {
                            continue;
                        }

                        // Drill down to the product information.
                        // There should be a div of class ProductMainDivClassName containing all the product information. Let's make sure there is
                        HtmlNode productMainNode = this.GetDescendantListByClassName(productPage.DocumentNode, "div",
                            ProductMainDivClassName).FirstOrDefault();                       
                        if (productMainNode == null)    // if we can't find the main product div, move on, there's nothing we can do
                        {
                            this.logger.WriteLineToLogFile("Main product div not found.");
                            continue;
                        }
                        
                        // Product description is the HTML content of the appropriate div on the main product page
                        String productDescription = this.GetProductDescription(productPage);

                        // Organic Matters product ID can come from several places on the page; a hidden input seems to be a good place
                        Int32 productId = this.GetOmProductId(productMainNode);

                        // OK. Now let's see if there are any radio buttons for different sizes
                        HtmlNode sizeRadioMainDivNode = this.GetDescendantListByClassName(productMainNode, "div", SizeDivClassName, true).FirstOrDefault();
                        List<HtmlNode> sizeRadioNodeList = sizeRadioMainDivNode == null ? new List<HtmlNode>() :
                            this.GetDescendantListByTypeAndAttribute(sizeRadioMainDivNode, "input", "type", "radio", true);

                        // If we have size radio buttons,, we'll hit the remote.php AJAX page with the appropriate arguments and parse each price and availability from JSON response.
                        // If there are not, we'll parse the price and availability from the page and that's all we got
                        if (sizeRadioNodeList.Any())
                        {
                            Boolean firstSize = true;     // We'll only retrieve the description for the first size of the product since it will be the same for all
                            foreach (HtmlNode radioNode in sizeRadioNodeList)
                            {
                                String jsonResult = String.Empty;
                                WebClient client = new WebClient();

                                try
                                {
                                    Byte[] response = client.UploadValues("http://www.omfoods.com/remote.php",
                                        new NameValueCollection()
                                        {
                                            {"product_id", productId.ToString(CultureInfo.InvariantCulture)},
                                            {this.GetAttributeValueByName(radioNode, "name"), this.GetAttributeValueByName(radioNode, "value")},
                                            {"w", "getProductAttributeDetails"}
                                        });

                                    jsonResult = System.Text.Encoding.UTF8.GetString(response);
                                }
                                catch (Exception ex)
                                {
                                    this.logger.WriteLineToLogFile("ERROR GETTING AJAX DATA FOR PRODUCT ID {0}: {1}", productId, ex.Message);
                                }
                                finally
                                {
                                    client.Dispose();
                                }

                                if (String.IsNullOrEmpty(jsonResult))
                                {
                                    this.logger.WriteLineToLogFile("RETRIEVED NO AJAX DATA FOR PRODUCT ID {0}", productId);
                                    continue;
                                }

                                OmProduct omProduct = JsonConvert.DeserializeObject<OmProduct>(jsonResult);

                                Product product = new Product
                                {
                                    Name = productTuple.Item1,
                                    Description = firstSize ? productDescription : String.Empty,
                                    Category = categoryTuple.Item1,
                                    OmId = productId,
                                    OmUrl = productTuple.Item2,
                                    Size = this.GetSizeDescriptionFromRadioButton(radioNode.ParentNode),
                                    Price = omProduct.details.unformattedPrice,
                                    Sku = omProduct.details.sku,
                                    IsInStock = omProduct.details.instock
                                };

                                if (String.IsNullOrEmpty(product.Sku))
                                {
                                    this.logger.WriteLineToLogFile("SKIPPING PRODUCT - NO SKU FOUND - {0}", product.Detail);
                                }
                                else
                                {
                                    productList.Add(product);
                                    firstSize = false;
                                }
                            }
                        }
                        else    // There are no size radio buttons, so we only have a single product to worry about
                        {
                            Product product = new Product
                            {
                                Name = productTuple.Item1,
                                Description = productDescription,
                                Category = categoryTuple.Item1,
                                OmId = productId,
                                OmUrl = productTuple.Item2,
                                Sku = this.GetSku(productMainNode),
                                Price = this.GetPrice(productPage.DocumentNode)
                            };
                            // Hacky way to determine if product is out of stock
                            product.IsInStock = product.Price > 0.00m
                                && !productPage.ToString().Contains(OutOfStockHack);
                            // product.Size is going to be empty if this is the only size available

                            if (String.IsNullOrEmpty(product.Sku))
                            {
                                this.logger.WriteLineToLogFile("SKIPPING PRODUCT - NO SKU FOUND - {0}", product.Detail);
                            }
                            else
                            {
                                productList.Add(product);
                            }
                        }

                        if (this.numberToProcess > 0 && ++productsProcessed >= this.numberToProcess)
                        {
                            break;
                        }
                    }
                }

                this.logger.WriteProductInfo(productList);
            }
            catch (Exception ex)
            {
                this.logger.BlankLineInConsoleAndLogFile();
                this.logger.BlankLineInConsoleAndLogFile();
                this.logger.WriteLineToConsoleAndLogFile("PARSING ERROR: {0}", ex.Message);
            }
        }

        #endregion ParseOmData

        #region Helper Methods

        /// <summary>
        /// Reads a page into an HtmlDocument
        /// </summary>
        /// <param name="url">Page URL</param>
        /// <param name="printOk">Optional boolean to suppress printing "OK" to the console when page is successfully parsed.</param>
        /// <param name="includeConsoleOutput">Include console output</param>
        /// <returns>HtmlDocument parsed from the given URL</returns>
        private HtmlDocument ReadPage(String url, Boolean printOk = true, Boolean includeConsoleOutput = true)
        {
            HtmlDocument html = null;
            String message = printOk ? "OK" : String.Empty;

            try
            {
                html = (new HtmlWeb()).Load(url);
            }
            catch (Exception ex)
            {
                message = String.Format("ERROR: {0}", ex.Message);
            }

            if (html == null)
            {
                message = String.Format("Failed to retrieve anything from {0}.", url);
            }

            if (!String.IsNullOrEmpty(message))
            {
                if (includeConsoleOutput)
                {
                    this.logger.WriteLineToConsoleAndLogFile(message);
                }
                else
                {
                    this.logger.WriteLineToLogFile(message);
                }
            }

            return html;
        }

        /// <summary>
        /// Gets all descendants of the given node that have the given type and attribute value
        /// </summary>
        /// <param name="parentNode">Parent node</param>
        /// <param name="descendantType">HTML tag type to look for</param>
        /// <param name="attributeName">Attribute name to look for</param>
        /// <param name="attributeValue">Attribute value to look for</param>
        /// <param name="strictComparison">Optional value to match the attribute value exactly</param>
        /// <returns>List of matching node descendants</returns>
        private List<HtmlNode> GetDescendantListByTypeAndAttribute(HtmlNode parentNode, String descendantType, String attributeName, String attributeValue, Boolean strictComparison = false)
        {
            return parentNode.Descendants(descendantType).Where(d => d.Attributes.Contains(attributeName) 
                && (strictComparison ? d.Attributes[attributeName].Value.Equals(attributeValue, StringComparison.Ordinal)
                    : d.Attributes[attributeName].Value.Contains(attributeValue))).ToList();
        }

        /// <summary>
        /// Gets all descendants of the given node that have the given type and class name
        /// </summary>
        /// <param name="parentNode">Parent node</param>
        /// <param name="descendantType">HTML tag type to look for</param>
        /// <param name="className">Class name to look for</param>
        /// <param name="strictComparison">Optional value to match the attribute value exactly</param>
        /// <returns>List of matching node descendants</returns>
        private List<HtmlNode> GetDescendantListByClassName(HtmlNode parentNode, String descendantType, String className, Boolean strictComparison = false)
        {
            return this.GetDescendantListByTypeAndAttribute(parentNode, descendantType, "class", className, strictComparison);
        }

        /// <summary>
        /// Gets all descendants of the given node that have the given type and itemprop attribute value
        /// </summary>
        /// <param name="parentNode">Parent node</param>
        /// <param name="descendantType">HTML tag type to look for</param>
        /// <param name="itempropValue">Itemprop attribute value to look for</param>
        /// <param name="strictComparison">Optional value to match the attribute value exactly</param>
        /// <returns>List of matching node descendants</returns>
        private List<HtmlNode> GetDescendantListByItempropName(HtmlNode parentNode, String descendantType, String itempropValue, Boolean strictComparison = false)
        {
            return this.GetDescendantListByTypeAndAttribute(parentNode, descendantType, "itemprop", itempropValue, strictComparison);
        }

        /// <summary>
        /// Gets the value of an attribute of a node by attribute name
        /// </summary>
        /// <param name="node">HtmlNode</param>
        /// <param name="attributeName">Attribute name</param>
        /// <returns>Value of the attribute by name</returns>
        private String GetAttributeValueByName(HtmlNode node, String attributeName)
        {
            return node != null && node.Attributes.Contains(attributeName) ? node.Attributes[attributeName].Value : String.Empty;
        }
        
        /// <summary>
        /// Takes a list of HtmlNodes, each of which should contain some anchor tags. Parses out information from all anchor tags in all nodes in the list,
        /// putting the inner text in the first item and the href URL in the other.
        /// </summary>
        /// <param name="nodeList">List of HtmlNodes to parse</param>
        /// <returns>A list of String, String tuples with each anchor's inner text as the first item and the href URL in the other.</returns>
        private List<Tuple<String, String>> GetUrlAndNameFromAnchors(IEnumerable<HtmlNode> nodeList)
        {
            List<Tuple<String, String>> tupleList = new List<Tuple<String, String>>();
            foreach (HtmlNode categoryNode in nodeList)
            {
                tupleList.AddRange(
                    categoryNode.Descendants("a")
                        .Select(node => Tuple.Create(node.InnerText, node.Attributes["href"].Value)));
            }
            return tupleList;
        }

        /// <summary>
        /// Parses the product name from the main product div element or the main product page title
        /// </summary>
        /// <param name="productPage">Entire product page document</param>
        /// <param name="productMainNode">Main product div node</param>
        /// <returns>Product name parsed from the page/div supplied</returns>
        //private String GetProductName(HtmlDocument productPage, HtmlNode productMainNode)
        //{
        //    // The product name should be in the main product div in a <h1> tag with itemprop="name" attribute
        //    String productName = String.Empty;
        //    HtmlNode nameNode = this.GetDescendantListByItempropName(productMainNode, "h1", NameH1Itemprop, true).FirstOrDefault();
        //    if (nameNode != null)
        //    {
        //        productName = nameNode.InnerText;
        //    }
        //    else // If the name node is not present, we can get the name from the title 
        //    {
        //        HtmlNode titleNode = productPage.DocumentNode.Descendants("title").SingleOrDefault();
        //        // This will throw an exception if there isn't one. Would FirstOrDefault be better?
        //        if (titleNode != null)
        //        {
        //            // The title seems to end with "... - Organic Matters", so let's strip that off if it's there
        //            productName = titleNode.InnerText;
        //            if (productName.EndsWith(" - Organic Matters", StringComparison.OrdinalIgnoreCase))
        //            {
        //                productName = productName.Substring(0,
        //                    productName.IndexOf(" - Organic Matters", StringComparison.OrdinalIgnoreCase));
        //            }
        //        }
        //    }

        //    return productName;
        //}

        /// <summary>
        /// Parses the product description - including HTML markup - from the main product page
        /// </summary>
        /// <param name="productPage">Entire product page document</param>
        /// <returns>Product name parsed from the page/div supplied</returns>
        private String GetProductDescription(HtmlDocument productPage)
        {
            HtmlNode descriptionNode = this.GetDescendantListByClassName(productPage.DocumentNode, "div",
                ProductDescriptionClassName).FirstOrDefault();

            return descriptionNode == null ? String.Empty : descriptionNode.InnerHtml.Trim();
        }

        /// <summary>
        /// Gets the Organic Matters product ID from the main product div node
        /// </summary>
        /// <param name="productNode">Main product div node</param>
        /// <returns>Organic Matters product ID</returns>
        private Int32 GetOmProductId(HtmlNode productNode)
        {
            // Organic Matters product ID can come from several places on the page; a hidden input seems to be a good place
            Int32 productId;
            HtmlNode productIdNode = productNode.Descendants("input").FirstOrDefault(
                d => d.Attributes.Contains("type") && d.Attributes["type"].Value.Equals("hidden", StringComparison.Ordinal)
                    && d.Attributes.Contains("name") && d.Attributes["name"].Value.Equals("product_id", StringComparison.Ordinal)
                    && d.Attributes.Contains("value"));
            if (productIdNode == null || !Int32.TryParse(productIdNode.Attributes["value"].Value, out productId))
            {
                productId = -1;
            }
            return productId;
        }

        /// <summary>
        /// Gets the product SKU  from the main product div node
        /// </summary>
        /// <param name="productNode">Main product div node</param>
        /// <returns>Product SKU string</returns>
        private String GetSku(HtmlNode productNode)
        {
            HtmlNode skuNode = this.GetDescendantListByItempropName(productNode, "span", SkuSpanItemprop, true).FirstOrDefault();
            return skuNode == null ? String.Empty : skuNode.InnerText.Trim();
        }

        /// <summary>
        /// Gets the size description from a label containing it
        /// </summary>
        /// <param name="labelNode">Label HtmlNode</param>
        /// <returns>Size description</returns>
        private String GetSizeDescriptionFromRadioButton(HtmlNode labelNode)
        {
            if (labelNode == null)
            {
                return String.Empty;
            }

            HtmlNode spanSizeName = this.GetDescendantListByClassName(labelNode, "span", "name", true).FirstOrDefault();
            return spanSizeName == null ? String.Empty : spanSizeName.InnerText;
        }

        /// <summary>
        /// Gets the product price from the main product div node
        /// </summary>
        /// <param name="productNode">Main product div node</param>
        /// <returns>Product price</returns>
        private Decimal GetPrice(HtmlNode productNode)
        {
            String priceString = String.Empty;
            
            // Get the price detail div. There should only be one. This is a div with class containing PriceClassName.
            HtmlNode priceRowNode = this.GetDescendantListByClassName(productNode, "div", PriceClassName, true).FirstOrDefault();
            if (priceRowNode == null)
            {
                this.logger.WriteLineToLogFile("Price row div not found.");
            }
            else
            {
                // Get the value of the content attribute of the price value meta tag from the price row div. There should only be one.
                HtmlNode priceNode = priceRowNode.Descendants("meta")
                    .FirstOrDefault(d => d.Attributes.Contains("itemprop") && d.Attributes.Contains("content")
                        && d.Attributes["itemprop"].Value.Equals(PriceMetaItemprop, StringComparison.Ordinal));
                if (priceNode == null)
                {
                    this.logger.WriteLineToLogFile("Price meta tag not found.");
                }
                else
                {
                    priceString = priceNode.Attributes["content"].Value;
                    if (priceString.StartsWith("$", StringComparison.Ordinal))
                    {
                        priceString = priceString.Substring(1);
                    }
                }
            }

            Decimal price;
            return !String.IsNullOrEmpty(priceString) && Decimal.TryParse(priceString, out price) ? price : 0.00m;
        }


        #endregion Helper Methods

        #region IDisposable members

        private void Dispose(bool disposing)
        {
            if (!disposed)
            {
                if (disposing)
                {
                    if (this.logger != null)
                    {
                        this.logger.Dispose();
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
