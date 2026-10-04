<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Products extends Controller
{
    private function api()
    {
        $this->call->database();
        $api = $this->call->library('api');
        $api->require_jwt();
        return $api;
    }

    private function db()
    {
        return $this->call->database();
    }

    private function body()
    {
        $body = json_decode(file_get_contents('php://input'), true);
        return is_array($body) ? $body : [];
    }

    private function validatedFields($input, $partial = false)
    {
        $fields = [];
        $errors = [];

        if (!$partial || array_key_exists('product_name', $input)) {
            $name = trim((string) ($input['product_name'] ?? ''));
            if ($name === '' || strlen($name) > 100) {
                $errors['product_name'] = 'Product name is required and must be at most 100 characters.';
            } else {
                $fields['product_name'] = $name;
            }
        }

        if (!$partial || array_key_exists('description', $input)) {
            if (isset($input['description']) && !is_string($input['description'])) {
                $errors['description'] = 'Description must be text.';
            } else {
                $fields['description'] = trim((string) ($input['description'] ?? ''));
            }
        }

        if (!$partial || array_key_exists('price', $input)) {
            $price = $input['price'] ?? null;
            if (!is_numeric($price) || (float) $price < 0 || (float) $price > 99999999.99) {
                $errors['price'] = 'Price must be a non-negative number with up to 8 digits before the decimal.';
            } else {
                $fields['price'] = number_format((float) $price, 2, '.', '');
            }
        }

        if (!$partial || array_key_exists('quantity', $input)) {
            $quantity = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT);
            if ($quantity === false || $quantity < 0) {
                $errors['quantity'] = 'Quantity must be a non-negative whole number.';
            } else {
                $fields['quantity'] = $quantity;
            }
        }

        return [$fields, $errors];
    }

    private function productExists($id)
    {
        return $this->db()->raw('SELECT id FROM products WHERE id = ? LIMIT 1', [$id])->fetch(PDO::FETCH_ASSOC);
    }

    public function index()
    {
        $api = $this->api();
        $rows = $this->db()->raw(
            'SELECT id, product_name, description, price, quantity, created_at FROM products ORDER BY created_at DESC, id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
        $api->respond(['products' => $rows]);
    }

    public function show($id)
    {
        $api = $this->api();
        if (!ctype_digit((string) $id) || (int) $id < 1) {
            $api->respond_error('Invalid product id.', 400);
        }
        $product = $this->db()->raw(
            'SELECT id, product_name, description, price, quantity, created_at FROM products WHERE id = ? LIMIT 1',
            [(int) $id]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $api->respond_error('Product not found.', 404);
        }
        $api->respond(['product' => $product]);
    }

    public function store()
    {
        $api = $this->api();
        [$fields, $errors] = $this->validatedFields($this->body());
        if ($errors) {
            $api->respond(['error' => 'Validation failed.', 'details' => $errors], 422);
        }

        $db = $this->db();
        $db->raw(
            'INSERT INTO products (product_name, description, price, quantity) VALUES (?, ?, ?, ?)',
            [$fields['product_name'], $fields['description'], $fields['price'], $fields['quantity']]
        );
        $id = (int) $db->last_id();
        $product = $db->raw(
            'SELECT id, product_name, description, price, quantity, created_at FROM products WHERE id = ?',
            [$id]
        )->fetch(PDO::FETCH_ASSOC);

        $api->respond(['message' => 'Product created.', 'product' => $product], 201);
    }

    public function update($id)
    {
        $api = $this->api();
        if (!ctype_digit((string) $id) || (int) $id < 1) {
            $api->respond_error('Invalid product id.', 400);
        }
        $db = $this->db();
        if (!$this->productExists((int) $id)) {
            $api->respond_error('Product not found.', 404);
        }

        [$fields, $errors] = $this->validatedFields($this->body(), true);
        if ($errors) {
            $api->respond(['error' => 'Validation failed.', 'details' => $errors], 422);
        }
        if (!$fields) {
            $api->respond_error('Provide at least one product field to update.', 422);
        }

        $assignments = [];
        foreach (array_keys($fields) as $field) {
            $assignments[] = $field . ' = ?';
        }
        $values = array_values($fields);
        $values[] = (int) $id;
        $db->raw('UPDATE products SET ' . implode(', ', $assignments) . ' WHERE id = ?', $values);
        $product = $db->raw(
            'SELECT id, product_name, description, price, quantity, created_at FROM products WHERE id = ?',
            [(int) $id]
        )->fetch(PDO::FETCH_ASSOC);

        $api->respond(['message' => 'Product updated.', 'product' => $product]);
    }

    public function destroy($id)
    {
        $api = $this->api();
        if (!ctype_digit((string) $id) || (int) $id < 1) {
            $api->respond_error('Invalid product id.', 400);
        }
        $deleted = $this->db()->raw('DELETE FROM products WHERE id = ?', [(int) $id])->rowCount();
        if (!$deleted) {
            $api->respond_error('Product not found.', 404);
        }
        $api->respond(['message' => 'Product deleted.']);
    }
}