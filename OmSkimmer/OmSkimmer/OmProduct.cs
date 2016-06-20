using System;
using Newtonsoft.Json;

namespace OmSkimmer
{
    public class OmProduct
    {
        #region Properties

        [JsonConverter(typeof(BoolConverter))]
        public Boolean success { get; set; }

        public OmProductDetail details { get; set; }

        #endregion Properties

        #region Constructor

        public OmProduct()
        {
            this.success = false;
            this.details = new OmProductDetail();
        }

        #endregion Constructor
    }

    public class OmProductDetail
    {
                            // {"success":1,"details":{"purchasable":true,"sku":"N100-25","instock":true,"unformattedPrice":"362.5","base":false,
                            //"baseImage":"http:\/\/cdn2.bigcommerce.com\/server400\/b83eb\/products\/646\/images\/1026\/ALMONDS__23360.1426194866.1000.1200.jpg?c=2",
                            //"baseThumb":"http:\/\/cdn2.bigcommerce.com\/server400\/b83eb\/products\/646\/images\/1026\/ALMONDS__23360.1426194866.490.490.jpg?c=2",
                            //"image":"http:\/\/cdn2.bigcommerce.com\/server400\/b83eb\/products\/646\/images\/1026\/ALMONDS__23360.1426194866.1000.1200.jpg?c=2",
                            //"thumb":"http:\/\/cdn2.bigcommerce.com\/server400\/b83eb\/products\/646\/images\/1026\/ALMONDS__23360.1426194866.490.490.jpg?c=2",
                            //"price":"$362.50","rrp":"$0.00","unformattedRrp":0,"priceLabel":"Price:"}}
        #region Properties

        public Boolean purchasable { get; set; }
        public String sku { get; set; }
        public Boolean instock { get; set; }
        public Decimal unformattedPrice { get; set; }
        public String baseImage { get; set; }
        public String baseThumb { get; set; }
        public String image { get; set; }
        public String thumb { get; set; }
        public String price { get; set; }
        public String rrp { get; set; }
        public Decimal unformattedRrp { get; set; }
        public String priceLabel { get; set; }

        #endregion Properties

        public OmProductDetail()
        {
            this.purchasable = false;
            this.sku = String.Empty;
            this.instock = false;
            this.unformattedPrice = 0.00m;
            this.baseImage = String.Empty;
            this.baseThumb = String.Empty;
            this.image = String.Empty;
            this.thumb = String.Empty;
            this.price = String.Empty;
            this.rrp = String.Empty;
            this.unformattedRrp = 0.00m;
            this.priceLabel = String.Empty;
        }

        #region Constructor

        #endregion Constructor
    }
}
