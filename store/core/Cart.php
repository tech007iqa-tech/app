<?php
// core/Cart.php

class Cart {
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
    }

    /**
     * Add a product ID to the cart
     */
    public function add($productId, $quantity = 1) {
        if (isset($_SESSION['cart'][$productId])) {
            $_SESSION['cart'][$productId] += $quantity;
        } else {
            $_SESSION['cart'][$productId] = $quantity;
        }
    }

    /**
     * Update product quantity
     */
    public function update($productId, $quantity) {
        if ($quantity <= 0) {
            $this->remove($productId);
        } else {
            $_SESSION['cart'][$productId] = $quantity;
        }
    }

    /**
     * Remove a product completely from the cart
     */
    public function remove($productId) {
        if (isset($_SESSION['cart'][$productId])) {
            unset($_SESSION['cart'][$productId]);
        }
    }

    /**
     * Clear the cart
     */
    public function clear() {
        $_SESSION['cart'] = [];
    }

    /**
     * Get the total number of items in the cart
     */
    public function getCount() {
        return array_sum($_SESSION['cart']);
    }

    /**
     * Get raw cart items [id => quantity]
     */
    public function getItems() {
        return $_SESSION['cart'];
    }
}
