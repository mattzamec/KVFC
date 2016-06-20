using System;

namespace OmSkimmer
{
    public class Product
    {
        #region Properties

        public String Name { get; set; }
        public String Size { get; set; }
        public Int32 OmId { get; set; }
        public String Sku { get; set; }
        public Decimal Price { get; set; }
        public Boolean IsInStock { get; set; }

        public String Detail
        {
            get
            {
                return String.Format("ID: {0}, SKU: {1}, Name: {2}, Size: {3}, Price: {4:C}, {5}",
                    this.OmId, this.Sku, this.Name, this.Size, this.Price, this.IsInStock ? "In stock" : "OUT OF STOCK");
            }
        }

        public String Csv
        {
            get
            {
                return String.Format("{0}, {1}{2}{3}, {4}, {5}",
                    this.Sku.Replace(',', '?'), this.Name.Replace(',', '?'), String.IsNullOrEmpty(this.Size) ? String.Empty : " ", this.Size.Replace(',', '?'), this.Price, this.IsInStock ? "In stock" : "OUT OF STOCK");
            }
        }

        #endregion Properties

        #region Constructor

        public Product()
        {
            this.Name = String.Empty;
            this.Size = String.Empty;
            this.OmId = -1;
            this.Sku = String.Empty;
            this.Price = 0.00m;
            this.IsInStock = false;
        }

        #endregion Constructor
    }
}
