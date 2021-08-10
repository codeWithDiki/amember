<?php

class Am_Pdf_Invoice_InvoicePayment extends Am_Pdf_Invoice_Abstract
{
    function __construct(InvoicePayment $payment)
    {
        $this->invoice = $payment->getInvoice();
        $this->payment = $payment;
    }

    public function isFirstPayment()
    {
        return $this->payment->isFirst();
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

            $pdf_file_name = $this->payment->pk() . '.payment';
            if(file_exists($pdf_file_dir . $pdf_file_name))
                return file_get_contents($pdf_file_dir . $pdf_file_name);
        }
        if (
            $this->getDi()->plugins_tax->getEnabled() &&
            (($this->invoice->tax_rate>0) || ($this->payment->tax_rate>0)) &&
            ($this->invoice->tax_rate != $this->payment->tax_rate)
        ) {
            $this->invoice = $this->invoice->recalculateWithTaxRate($this->payment->tax_rate);
        }

        $invoice = $this->invoice;
        $payment = $this->payment;
        $user = $invoice->getUser();

        $pdf = $this->createPdfTemplate();

        $event = new Am_Event(Am_Event::PDF_INVOICE_BEFORE_RENDER, [
            'amPdfInvoice' => $this,
            'pdf' => $pdf,
            'invoice' => $invoice,
            'payment' => $payment,
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
        $leftCol->invoiceNumber = ___('Invoice Number: ') . $payment->getDisplayInvoiceId();
        $leftCol->date = ___('Date: ') . amDate($payment->dattm);

        $this->getDi()->hook->call(Am_Event::PDF_INVOICE_COL_LEFT, [
            'col' => $leftCol,
            'invoice' => $invoice,
            'payment' => $payment,
            'user' => $user
        ]);

        foreach ($leftCol as $line) {
            $pointerL = $page->drawTextWithFixedWidth($line, $left, $pointerL, $width_coll, 'UTF-8');
        }

        $rightCol = new Am_Pdf_Invoice_Col();
        $rightCol->name = $invoice->getName();
        $rightCol->email = $invoice->getEmail();
        $rightCol->address = $payment->getStreet1();
        if ($payment->getStreet2()) {
            $rightCol->address2 = $payment->getStreet2();
        }
        $rightCol->city = trim(sprintf("%s, %s %s",  $payment->getCity(), $this->getState($payment), $payment->getZip()), ', ');
        $rightCol->country = $this->getCountry($payment);
        if ($user->tax_id)
        {
            $rightCol->taxId = ___('EU VAT ID: ') . $user->tax_id;
        }

        $this->getDi()->hook->call(Am_Event::PDF_INVOICE_COL_RIGHT, [
            'col' => $rightCol,
            'invoice' => $invoice,
            'payment' => $payment,
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
            'payment' => $payment,
            'user' => $user,
            'amPdfInvoice' => $this
        ]);

        if ($this->getDi()->config->get('invoice_include_access')) {
            $pointer = $this->renderAccess($page, $pointer);
        }

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

        $table->addRow(
            '#',
            ___('Subscription/Product Title'),
            ___('Qty'),
            ___('Unit Price'),
            ___('Total Price'));

        $num = 0;
        $taxes = [];
        $is_first = $this->isFirstPayment();
        $prefix =  $is_first ? 'first_' : 'second_';
        foreach ($invoice->getItems() as $p)
        {
            if (!$is_first && !$p->rebill_times) {
                //skip not recurring items
                continue;
            }

            if ($p->tax_rate && $p->{$prefix . 'tax'}) {
                if (!isset($taxes[$p->tax_rate])) {
                    $taxes[$p->tax_rate] = 0;
                }
                $taxes[$p->tax_rate] += $p->{$prefix . 'tax'};
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

        $table->setStyleForColumn(2, [
            'align' => 'right',
            'width' => $width_total
        ]);
        $table->setStyleForColumn(1, [
            'align' => 'right',
        ]);

        $subtotal = (float) ($is_first ? $invoice->first_subtotal : $invoice->second_subtotal);
        $total = (float) ($is_first ? $invoice->first_total : $invoice->second_total);
        $discount = (float) ($is_first ? $invoice->first_discount : $invoice->second_discount);
        $shipping = (float) ($is_first ? $invoice->first_shipping : $invoice->second_shipping);
        $tax = (float) ($is_first ? $invoice->first_tax : $invoice->second_tax);
        if (!$taxes) {
            $taxes[$invoice->tax_rate] = $tax;
        }

        $total_rows = [];

        if ($discount || $shipping) {
            $total_rows['subtotal'] = [___('Subtotal'), $invoice->getCurrency($subtotal), $styleBold];
        }

        if ($discount) {
            $total_rows['discount'] = [___('Discount'), '- ' . $invoice->getCurrency($discount)];
        }

        if ($shipping) {
            $total_rows['shipping'] = [___('Shipping'), $invoice->getCurrency($shipping)];
        }

        if ($tax || (Am_Di::getInstance()->plugins_tax->getEnabled() && $this->getDi()->config->get('invoice_always_tax'))) {
            $total_rows['taxable_subtotal'] = [___('Taxable Subtotal'), $invoice->getCurrency($subtotal - $discount)];
            $i=1;
            foreach ($taxes as $rate => $_) {
                $total_rows['tax_' . $i++] = [___('Tax Amount') . sprintf(' (%s%%)', (float)$rate), $invoice->getCurrency($_)];
            }
        }

        $total_rows['total'] = [___('Total'), $invoice->getCurrency($total), $styleBold];

        if (!$this->getDi()->config->get('different_invoice_for_refunds'))
        {
            $refunds = $this->getDi()->invoiceRefundTable->findBy(['invoice_payment_id' => $payment->pk()]);
            if ($refunds) {
                $i=1;
                $refunds_total = 0;
                foreach ($refunds as $r) {
                    $refunds_total += $r->amount;
                    $total_rows['refund_' . $i++] = [___('Refund') . "<br/>" . amDatetime($r->dattm), "-" . $invoice->getCurrency($r->amount), $styleBold];
                }

                $total_rows['paid'] = [___('Amount Paid'), $invoice->getCurrency(sprintf("%.2f", $payment->amount - $refunds_total)), $styleBold];
            }
        }

        $total_rows = $this->getDi()->hook->filter($total_rows, Am_Event::PDF_INVOICE_TOTALS, [
            'invoice' => $invoice,
            'payment' => $payment,
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

        if (!$this->getDi()->config->get('invoice_do_not_include_terms')) {
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
            'payment' => $payment,
            'user' => $user,
            'amPdfInvoice' => $this
        ]);

        if (!$this->getDi()->config->get('invoice_custom_template') ||
            !$this->getDi()->uploadTable->load($this->getDi()->config->get('invoice_custom_template'))) {

            if ($ifn = $this->getDi()->config->get('invoice_footer_note')) {
                $tmpl = new Am_SimpleTemplate();
                $tmpl->assignStdVars();
                $tmpl->assign('user', $user);
                $tmpl->assign('invoice', $invoice);
                $tmpl->assign('payment', $payment);
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

    public function renderAccess($page, $pointer)
    {
        $invoice = $this->invoice;
        //if user is not approved there is no access records
        $accessrecords = $invoice->getAccessRecords();
        if (!$accessrecords) {
            return $pointer;
        }
        $payment = $this->payment;

        $padd = 40;
        $width_date = 120;

        $fontHB = $this->getFontBold();

        $table = new Am_Pdf_Table();
        $table->setMargin($padd, $padd, $padd, $padd);
        $table->setStyleForRow(1, [
            'shape' => [
                'type' => Zend_Pdf_Page::SHAPE_DRAW_STROKE,
                'color' => new Zend_Pdf_Color_Html("#cccccc")
            ],
            'font' => [
                'face' => $fontHB,
                'size' => 10
            ]
        ]);

        $table->setStyleForColumn(//from
            2, [
                'width' => $width_date
            ]
        );
        $table->setStyleForColumn(//to
            3, [
                'width' => $width_date
            ]
        );

        $table->addRow(
            ___('Subscription/Product Title'),
            ___('Begin'),
            ___('Expire'));

        $productOptions = $this->getDi()->productTable
            ->getProductTitles(array_map(function($a) {return $a->product_id;}, $accessrecords));

        foreach ($accessrecords as $a) {
            /* @var $a Access */
            if ($a->invoice_payment_id != $payment->pk()) {
                continue;
            }
            $table->addRow($productOptions[$a->product_id],
                amDate($a->begin_date),
                ($a->expire_date == Am_Period::MAX_SQL_DATE) ? ___('Lifetime') :
                    ($a->expire_date == Am_Period::RECURRING_SQL_DATE ?  ___('Recurring') : amDate($a->expire_date)));
        }

        return $page->drawTable($table, 0, $pointer);
    }
}