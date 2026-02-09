<?php
/**
 * script/mailer_templates.php - E-Mail Templates für v3.0
 */

/**
 * Generiert Bestätigungs-E-Mail für neue/bearbeitete Bestellung
 */
function generate_order_confirmation_email($order_data, $project, $edit_url) {
    $order_id = $order_data['order_id'];
    $guest_name = $order_data['guest']['firstname'] . ' ' . $order_data['guest']['lastname'];
    $project_name = $project['name'];
    
    $subject = "Bestellbestätigung: {$project_name}";
    
    // Plain text + HTML mit order-id
    $body_text = "Hallo {$guest_name},\n\n";
    $body_text .= "vielen Dank für Ihre Menü-Bestellung für das Event \"{$project_name}\"!\n\n";
    $body_text .= "═══════════════════════════════════════════\n";
    $body_text .= "Ihre Order-ID: {$order_id}\n";
    $body_text .= "═══════════════════════════════════════════\n\n";
    $body_text .= "Bitte notieren Sie diese ID. Sie benötigen sie, um Ihre Bestellung später zu bearbeiten.\n\n";
    $body_text .= "Bearbeiten Sie Ihre Bestellung hier:\n{$edit_url}\n\n";
    
    $body_text .= "─────────────────────────────────────────\n";
    $body_text .= "IHRE BESTELLUNG\n";
    $body_text .= "─────────────────────────────────────────\n\n";
    
    // Personen und Gerichte auflisten
    foreach ($order_data['persons'] as $idx => $person) {
        if ($idx === 0) {
            $body_text .= "👤 {$person['name']} (Hauptperson)\n";
        } else {
            $type_label = ($person['type'] === 'child') ? '👶 Kind' : '👤 Erwachsener';
            $age_label = ($person['age_group']) ? " ({$person['age_group']} Jahre)" : '';
            $body_text .= "{$type_label} {$person['name']}{$age_label}\n";
        }
        
        // Gerichte für diese Person
        $person_dishes = array_filter($order_data['orders'], function($o) use ($idx) {
            return $o['person_index'] === $idx;
        });
        
        foreach ($person_dishes as $dish_order) {
            $body_text .= "   • {$dish_order['category_name']}: {$dish_order['dish_name']}";
            if ($project['show_prices'] && isset($dish_order['price']) && $dish_order['price'] > 0) {
                $body_text .= " (" . number_format($dish_order['price'], 2, ',', '.') . " €)";
            }
            $body_text .= "\n";
        }
        $body_text .= "\n";
    }
    
    if ($project['show_prices']) {
        $total = 0;
        foreach ($order_data['orders'] as $dish_order) {
            $total += floatval($dish_order['price'] ?? 0);
        }
        $body_text .= "─────────────────────────────────────────\n";
        $body_text .= "Gesamtsumme (Brutto): " . number_format($total, 2, ',', '.') . " €\n";
        $body_text .= "─────────────────────────────────────────\n\n";
    }
    
    $body_text .= "Haben Sie Fragen? Kontaktieren Sie uns gerne.\n\n";
    $body_text .= "Freundliche Grüße\n";
    $body_text .= "Ihr EMOS Team\n";
    
    // HTML Version
    $body_html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0d6efd; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .order-id-box { background: #f8f9fa; border: 2px solid #0d6efd; padding: 20px; margin: 20px 0; text-align: center; border-radius: 8px; }
            .order-id { font-size: 1.3em; font-weight: bold; color: #0d6efd; font-family: monospace; }
            .btn { display: inline-block; padding: 12px 24px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            .person-block { background: #f8f9fa; padding: 15px; margin: 10px 0; border-left: 4px solid #0d6efd; border-radius: 4px; }
            .dish-item { margin: 5px 0 5px 20px; }
            .total { background: #e7f3ff; padding: 15px; margin: 20px 0; font-weight: bold; border-radius: 4px; }
            .footer { text-align: center; color: #666; font-size: 0.9em; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🍽️ Bestellbestätigung</h1>
                <p>{$project_name}</p>
            </div>
            
            <p>Hallo <strong>{$guest_name}</strong>,</p>
            <p>vielen Dank für Ihre Menü-Bestellung!</p>
            
            <div class='order-id-box'>
                <p style='margin: 0 0 10px 0;'><strong>Ihre Order-ID:</strong></p>
                <div class='order-id'>{$order_id}</div>
                <p style='margin: 15px 0 0 0; font-size: 0.9em; color: #666;'>
                    Bitte notieren Sie diese ID zur späteren Bearbeitung.
                </p>
            </div>
            
            <div style='text-align: center;'>
                <a href='{$edit_url}' class='btn'>✏️ Bestellung bearbeiten</a>
            </div>
            
            <h3 style='margin-top: 30px;'>Ihre Bestellung:</h3>
    ";
    
    foreach ($order_data['persons'] as $idx => $person) {
        $person_label = ($idx === 0) ? "👤 {$person['name']} <small>(Hauptperson)</small>" : "";
        if ($idx > 0) {
            $type_icon = ($person['type'] === 'child') ? '👶' : '👤';
            $age_label = ($person['age_group']) ? " ({$person['age_group']} Jahre)" : '';
            $person_label = "{$type_icon} {$person['name']}{$age_label}";
        }
        
        $body_html .= "<div class='person-block'><strong>{$person_label}</strong><br>";
        
        $person_dishes = array_filter($order_data['orders'], function($o) use ($idx) {
            return $o['person_index'] === $idx;
        });
        
        foreach ($person_dishes as $dish_order) {
            $price_str = "";
            if ($project['show_prices'] && isset($dish_order['price']) && $dish_order['price'] > 0) {
                $price_str = " <span style='color: #666;'>(" . number_format($dish_order['price'], 2, ',', '.') . " €)</span>";
            }
            $body_html .= "<div class='dish-item'>• <strong>{$dish_order['category_name']}:</strong> {$dish_order['dish_name']}{$price_str}</div>";
        }
        
        $body_html .= "</div>";
    }
    
    if ($project['show_prices']) {
        $total = 0;
        foreach ($order_data['orders'] as $dish_order) {
            $total += floatval($dish_order['price'] ?? 0);
        }
        $body_html .= "<div class='total'>Gesamtsumme (Brutto): " . number_format($total, 2, ',', '.') . " €</div>";
    }
    
    $body_html .= "
            <div class='footer'>
                <p>Haben Sie Fragen? Kontaktieren Sie uns gerne.</p>
                <p>Freundliche Grüße<br>Ihr EMOS Team</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return [
        'subject' => $subject,
        'body_text' => $body_text,
        'body_html' => $body_html
    ];
}
