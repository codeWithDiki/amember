<?php

class Am_Pdf_Invoice_InvoiceRefund extends Am_Pdf_Invoice_Abstract
{
    /**
     * @var InvoiceRefund
     */
    protected $payment;

    /**
     * @var InvoicePayment
     */
    protected $origPayment;

    function __construct(InvoiceRefund $refund)
    {
        $this->invoice = $refund->getInvoice();
        $this->payment = $refund;
    }

    public function render()
    {
        if(Am_Di::getInstance()->config->get('store_pdf_file'))
        {
            $pdf_file_dir = Am_Di::getInstance()->data_dir . '/pdf' . date("/Y/m/", amstrtotime($this->payment->dattm));

            $event = new Am_Event(Am_Event::GET_PDF_FILES_DIR, ['payment' => $this->payment]);
            $event->setReturn($pdf_file_dir);
            $this->getDi()->hook->call($event);
            $pdf_file_dir = $event->getReturn();

            $pdf_file_name = $this->payment->pk() . '.refund';
            if(@file_exists($pdf_file_dir . $pdf_file_name))
                return file_get_contents($pdf_file_dir . $pdf_file_name);
        }
        $invoice = $this->invoice;
        $refund = $this->payment;
        $user = $invoice->getUser();

        $pdf = $this->createPdfTemplate();

        $event = new Am_Event(Am_Event::PDF_INVOICE_BEFORE_RENDER, [
            'amPdfInvoice' => $this,
            'pdf' => $pdf,
            'invoice' => $invoice,
            'payment' => $refund,
            'user' => $user
        ]);
        $event->setReturn(false);
        $this->getDi()->hook->call($event);

        // If event processing already rendered the Pdf.
        if ($event->getReturn() === true) {
            return $pdf->render();
        }

        $width_num = 30;
        $width_qty = 40;
        $width_price = 80;
        $width_total = 120;

        $padd = 40;
        $left = $padd;
        $right = $this->getPaperWidth() - $padd;
        $width_coll = floor(($this->getPaperWidth() - 3 * $padd)/2);

        $fontH = $this->getFontRegular();
        $fontHB = $this->getFontBold();

        $styleBold = [
            'font' => [
                'face' => $fontHB,
                'size' => 10
            ]
        ];

        $page = new Am_Pdf_Page_Decorator($pdf->pages[0]);
        $page->setFont($fontH, 10);

        $pointer = $this->getPointer();
        $pointerL = $pointerR = $pointer;

        $leftCol = new Am_Pdf_Invoice_Col();
        $leftCol->invoiceNumber = ___('Corrective Invoice Number: ') . $refund->getDisplayInvoiceId();
        $leftCol->recInvoiceNumber = ___('Rectifies Invoice Number: ') . $this->getOrigPayment()->getDisplayInvoiceId();
        $leftCol->date = ___('Date: ') . amDate($refund->dattm);

        $this->getDi()->hook->call(Am_Event::PDF_INVOICE_COL_LEFT, [
            'col' => $leftCol,
            'invoice' => $invoice,
            'payment' => $refund,
            'user' => $user
        ]);

        foreach ($leftCol as $line) {
            $pointerL = $page->drawTextWithFixedWidth($line, $left, $pointerL, $width_coll, 'UTF-8');
        }

        $rightCol = new Am_Pdf_Invoice_Col();
        $rightCol->name = $invoice->getName();
        $rightCol->email = $invoice->getEmail();
        $rightCol->address = $invoice->getStreet1();
        if ($invoice->getStreet2()) {
            $rightCol->address2 = $invoice->getStreet2();
        }
        $rightCol->city = trim(sprintf("%s, %s %s",  $invoice->getCity(), $invoice->getState(), $invoice->getZip()), ', ');
        $rightCol->country = $this->getCountry($invoice);

        if ($user->tax_id) {
            $rightCol->taxId = ___('EU VAT ID: ') . $user->tax_id;
        }

        $this->getDi()->hook->call(Am_Event::PDF_INVOICE_COL_RIGHT, [
            'col' => $rightCol,
            'invoice' => $invoice,
            'payment' => $refund,
            'user' => $user
        ]);

        $lineLength = 0;
        foreach ($rightCol as $line) {
            $lineLength = max($lineLength, $page->widthForString($line));
        }
        $lineLength = min($lineLength, $width_coll);

        foreach ($rightCol as $line) {
            $pointerR = $page->drawTextWithFixedWidth($line, $right - $lineLength, $pointerR, $lineLength, 'UTF-8');
        }

        $pointer = min($pointerR, $pointerL);

        $p = new stdClass();
        $p->value = & $pointer;

        $this->getDi()->hook->call(Am_Event::PDF_INVOICE_BEFORE_TABLE, [
            'page' => $page,
            'pointer' => $p,
            'invoice' => $invoice,
            'payment' => $refund,
            'user' => $user,
            'amPdfInvoice' => $this
        ]);

        $table = new Am_Pdf_Table();
        $table->setMargin($padd, $padd, 0, $padd);
        $table->setStyleForRow(
            1, [
                'shape' => [
                    'type' => Zend_Pdf_Page::SHAPE_DRAW_STROKE,
                    'color' => new Zend_Pdf_Color_Html("#cccccc")
                ],
                'font' => [
                    'face' => $fontHB,
                    'size' => 10
                ]
            ]
        );

        $table->setStyleForColumn(//num
            1, [
                'align' => 'right',
                'width' => $width_num
            ]
        );
        $table->setStyleForColumn(//qty
            3, [
                'align' => 'right',
                'width' => $width_qty
            ]
        );
        $table->setStyleForColumn(//price
            4, [
                'align' => 'right',
                'width' => $width_price
            ]
        );
        $table->setStyleForColumn(//total
            5, [
                'align' => 'right',
                'width' => $width_total
            ]
        );

        $table->addRow([
            '#',
            ___('Subscription/Product Title'),
            ___('Qty'),
            ___('Unit Price'),
            ___('Total Price')
        ]);

        $num = 0;
        $is_first = $this->isFirstPayment();
        foreach ($invoice->getItems() as $p)
        {
            if (!$is_first && !$p->rebill_times) {
                //skip not recurring items
                continue;
            }

            $item_title = $p->item_title;
            $options = [];
            foreach($p->getOptions() as $optKey => $opt) {
                $options[] = sprintf('%s: %s',
                    strip_tags($opt['optionLabel']),
                    implode(', ', array_map('strip_tags', (array)$opt['valueLabel'])));
            }
            if ($options) {
                $item_title .= sprintf(' (%s)', implode(', ', $options));
            }
            if ($this->getDi()->config->get('invoice_include_description')) {
                if ($p->item_description) {
                    $item_title .= "<br/>" . html_entity_decode(strip_tags($p->item_description));
                }
            }
            /* @var $p InvoiceItem */
            $table->addRow([
                ++$num . '.',
                $item_title,
                $p->qty,
                $invoice->getCurrency($is_first ? $p->first_price : $p->second_price),
                $invoice->getCurrency($is_first ? $p->getFirstSubtotal() : $p->getSecondSubtotal())
            ]);
        }

        $pointer = $page->drawTable($table, 0, $pointer);

        $table = new Am_Pdf_Table();
        $table->setMargin($padd/2, $padd, 0, $padd);

        $table->setStyleForColumn(
            2, [
                'align' => 'right',
                'width' => $width_total
            ]
        );
        $table->setStyleForColumn(1, [
            'align' => 'right',
        ]);

        $subtotal = $is_first ? $invoice->first_subtotal : $invoice->second_subtotal;
        $total = $is_first ? $invoice->first_total : $invoice->second_total;

        $total_rows = [];

        if ($subtotal != $total) {
            $total_rows['subtotal'] = [___('Subtotal'), $invoice->getCurrency($subtotal), $styleBold];
        }

        if (($is_first && $invoice->first_discount > 0) ||
            (!$is_first && $invoice->second_discount > 0))
        {
            $total_rows['discount'] = [___('Coupon Discount'), $invoice->getCurrency($is_first ? $invoice->first_discount : $invoice->second_discount)];
        }

        $total_rows['total'] = [___('Total'), $invoice->getCurrency($total), $styleBold];

        $total_rows['taxable_income'] = [___('Taxable Income'), "-" . $invoice->getCurrency($this->getOrigPayment()->amount - $this->getOrigPayment()->tax), $styleBold];

        if ($this->getOrigPayment()->tax > 0) {
            $total_rows['tax_1'] = [
                ___('Tax') . " " . floatval($invoice->tax_rate) . "%",
                "-" . $invoice->getCurrency($this->getOrigPayment()->tax),
                $styleBold
            ];
        }

        $total_rows['paid'] = [___('Amount Paid'), $invoice->getCurrency(sprintf("%.2f", $this->getOrigPayment()->amount - $this->payment->amount)), $styleBold];

        $total_rows = $this->getDi()->hook->filter($total_rows, Am_Event::PDF_INVOICE_TOTALS, [
            'invoice' => $invoice,
            'payment' => $refund,
            'user' => $user,
            'styleBold' => $styleBold,
        ]);

        foreach ($total_rows as [$title, $val, $style]) {
            $table->addRow($title, $val)->addStyle($style ?? []);
        }

        $x = $this->getPaperWidth() - ($width_qty + $width_price + $width_total) - 2 * $padd;
        $pointer = $page->drawTable($table, $x, $pointer);
        $page->nl($pointer);
        $page->nl($pointer);

        if (!$this->getDi()->config->get('invoice_do_not_include_terms'))
        {
            $termsText = new Am_TermsText($invoice);
            $page->drawTextWithFixedWidth(___('Subscription Terms') . ': ' . $termsText, $left, $pointer, $this->getPaperWidth() - 2 * $padd);
            $page->nl($pointer);
        }

        $p = new stdClass();
        $p->value = & $pointer;

        $this->getDi()->hook->call(Am_Event::PDF_INVOICE_AFTER_TABLE, [
            'page' => $page,
            'pointer' => $p,
            'invoice' => $invoice,
            'payment' => $refund,
            'user' => $user,
            'amPdfInvoice' => $this
        ]);

        if (!$this->getDi()->config->get('invoice_custom_template') ||
            !$this->getDi()->uploadTable->load($this->getDi()->config->get('invoice_custom_template')))
        {
            if ($ifn = $this->getDi()->config->get('invoice_footer_note'))
            {
                $tmpl = new Am_SimpleTemplate();
                $tmpl->assignStdVars();
                $tmpl->assign('user', $user);
                $tmpl->assign('invoice', $invoice);
                $ifn = $tmpl->render($ifn);

                $page->nl($pointer);
                $page->drawTextWithFixedWidth($ifn, $left, $pointer, $this->getPaperWidth() - 2 * $padd);
            }
        }
        $res = $pdf->render();
        if(Am_Di::getInstance()->config->get('store_pdf_file'))
        {
            if(!@is_dir($pdf_file_dir))
            {
                if(@mkdir($pdf_file_dir, 0755, true) === false)
                {
                    Am_Di::getInstance()->logger->error("Cannot create folder [$pdf_file_dir] in " . __METHOD__);
                    return $res;
                }
            }
            if (@file_put_contents($pdf_file_dir . $pdf_file_name , $res) === false)
            {
                Am_Di::getInstance()->logger->error("Cannot create file [{$pdf_file_name}$pdf_file_dir] in " . __METHOD__);
                return $res;
            }
        }
        return $res;
    }

    function isFirstPayment()
    {
        return $this->getOrigPayment()->isFirst();
    }

    /**
     * @return InvoicePayment
     */
    function getOrigPayment()
    {
        if (empty($this->origPayment))
        {
            if ($this->payment->invoice_payment_id) {
                $this->origPayment = $this->getDi()->invoicePaymentTable->load($this->payment->invoice_payment_id);
            } else {
                // Refund is not assigned to payment, get last payment which was not refunded..
                $payments  = $this->getDi()->invoicePaymentTable->selectObjects(""
                    . "SELECT p.*, r.invoice_refund_id "
                    . "FROM  ?_invoice_payment p LEFT JOIN  ?_invoice_refund r "
                    . "ON p.invoice_payment_id = r.invoice_payment_id "
                    . "WHERE p.invoice_id=? AND p.dattm <= ?  "
                    . "HAVING r.invoice_refund_id IS NULL "
                    . "ORDER BY p.dattm DESC", $this->payment->invoice_id, $this->payment->dattm);
                $this->origPayment = $payments[0];
            }
        }
        return $this->origPayment;
    }
}