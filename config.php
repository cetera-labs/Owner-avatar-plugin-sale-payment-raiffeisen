<?php
if (class_exists("\Sale\Payment")) {
    try {
        \Sale\Payment::addGateway('\SalePaymentRaiffeisen\Gateway');
    } catch (\Exception $e) {
    }
}