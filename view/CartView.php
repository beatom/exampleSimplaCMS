<?PHP

require_once('View.php');

class CartView extends View
{


    function fetch()
    {

        $product_url = $this->request->get('product_url', 'string');

        if (empty($product_url)) {

            return FALSE;
        }

        // Выбираем товар из базы
        $product = $this->products->get_product((string)$product_url);

        if (empty($product->resource)) {

            return FALSE;
        }

        header('location: ' . $product->resource);

        exit('<script>window.location.href="' . $product->resource . '"</script>');
    }

}