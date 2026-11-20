<?php
/**
 * Plugin Name: Apparatenman – Professionele Betaal- & Bedankpagina
 * Description: Maakt van de standaard WooCommerce "Betaal je bestelling"-pagina een professionele, inlogvrije afrekenpagina in de Apparatenman-huisstijl. De klant vult zelf zijn factuur-/verzendgegevens in; artikel en prijs staan vast. Voegt daarnaast een bedankpagina in huisstijl toe die geldt voor álle bestellingen. Werkt met Mollie Payments for WooCommerce.
 * Version: 1.6.0
 * Author: Apparatenman
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * Text Domain: witgoed-betaalpagina
 * Update URI: false
 *
 * Changelog:
 *  1.6.0 – Verwerking van de review van Outlawz (19-11-2026): ingevulde gegevens blijven staan na een fout, het adres
 *          wordt altijd opgeslagen of de betaling stopt met een melding, verzendadres en postcode worden gecontroleerd,
 *          land alleen uit de landen waar de winkel verkoopt/levert, bedankpagina met eigen tekst per orderstatus,
 *          e-mailverificatie alleen nog uit op de betaalpagina en kort na een betaalpoging, geen Google Fonts meer,
 *          AM Coeur-token in een eigen optie zonder autoload, opmaak afgestemd op het Sage-thema.
 *  1.5.0 – Productpagina: levertijd uit AM Coeur (leverbelofte) in plaats van de kale "Op voorraad"-regel, en een
 *          vertrouwensblok onder de koopknop met telefoon/WhatsApp, de actieve betaalmethoden en een reviewlink.
 *          Instellen via WooCommerce → Productpagina. Werkt zonder aanpassing van het thema.
 *  1.4.0 – Google Analytics "purchase"-event gerepareerd: op dit thema (Sage/Blade) wordt de bedankpagina
 *          gerenderd vóórdat wp_head() draait, waardoor Site Kit zijn aankoop-script kwijtraakt (de order
 *          wordt wél als "getrackt" gemarkeerd, maar er gaat niets naar Analytics/Ads). De plugin stuurt het
 *          event nu zelf, één keer per bestelling. Consent-koppeling (1.3.0) staat uit zodra Complianz Premium
 *          actief is, omdat die dit zelf doet.
 *  1.3.0 – Google Consent Mode-koppeling: de keuze in de Complianz-cookiebanner (statistieken/marketing) wordt direct doorgegeven aan de Google-tag van Site Kit (Analytics + Google Ads).
 *  1.2.5 – Handmatige bestellingen die door Mollie (verlopen/afgebroken betaalpoging) of een ander systeemproces werden geannuleerd, worden automatisch teruggezet op "In afwachting van betaling". Zelf annuleren in het beheer blijft gewoon mogelijk.
 *  1.2.4 – "Opnieuw controleren" op de Updates-pagina haalt nu direct de nieuwste versie op (geen wachttijd meer door cache).
 *  1.2.3 – Verwarrende rode melding "Je betaalt voor een gastbestelling" vervangen door een vriendelijke welkomsttekst in huisstijl.
 *  1.2.2 – Handmatig aangemaakte bestellingen vervallen niet meer automatisch; de betaallink blijft geldig totdat er betaald is (of je de order zelf annuleert).
 *  1.2.1 – Dubbele adresvelden (aparte Straat/Nummer/Toevoeging van adres-splitsende plugins) verborgen op de betaalpagina.
 *  1.2.0 – Automatische updates toegevoegd (toont een "Update beschikbaar"-knop in het dashboard).
 *  1.1.0 – Huisstijl + bedankpagina.
 *  1.0.0 – Eerste versie.
 *
 * Hoe het werkt:
 *  1. Maak in WooCommerce een handmatige bestelling aan (status "In afwachting van betaling").
 *  2. Vul artikel + prijs in en (optioneel) alvast de klantgegevens.
 *  3. Stuur de klant de "Betaalpagina van klant"-link (of e-mail de factuur).
 *  4. De klant landt op een nette pagina in huisstijl, vult/controleert zijn gegevens en
 *     rekent af met de gebruikelijke betaalmethoden (iDEAL, creditcard, Bancontact ... via Mollie).
 *     Er is GEEN account of wachtwoord nodig. Artikel en prijs kan de klant niet wijzigen.
 *  5. Na betaling komt élke klant (ook via de normale webshop-checkout) op de bedankpagina
 *     in huisstijl terecht.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Directe toegang blokkeren.
}

/**
 * ===== AUTO-UPDATE BRON =====
 * URL naar het update-informatiebestand (JSON). Zolang dit bestand bestaat en
 * een hoger versienummer bevat, verschijnt er in WordPress automatisch een
 * "Update beschikbaar"-melding bij deze plugin.
 *
 * Je kunt deze waarde ook in wp-config.php zetten (define('WTBP_UPDATE_URL', '...'))
 * of via de filter 'wtbp_update_url' aanpassen. Leeg laten = auto-update uit.
 * Zie de bureau-instructie voor het aanmaken van dit bestand.
 */
if ( ! defined( 'WTBP_VERSION' ) ) {
	define( 'WTBP_VERSION', '1.6.0' );
}
if ( ! defined( 'WTBP_UPDATE_URL' ) ) {
	define( 'WTBP_UPDATE_URL', 'https://raw.githubusercontent.com/AMCoeur/apparatenman-betaalpagina/main/witgoed-betaalpagina.json' );
}

/**
 * Zorg dat WooCommerce actief is voordat we iets doen.
 */
add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>Apparatenman – Professionele Betaal- & Bedankpagina</strong> vereist dat WooCommerce actief is.</p></div>';
		} );
	}
} );

/* =========================================================================
 * 0. HUISSTIJL (kleuren, lettertype, logo)
 * =========================================================================
 * Automatisch afgeleid van apparatenman.nl. Alles is via de filter
 * 'wtbp_brand' aan te passen (zie handleiding), zodat je nooit door de
 * plugincode hoeft te zoeken om een kleur te wijzigen.
 * ========================================================================= */
function wtbp_brand() {
	return apply_filters( 'wtbp_brand', array(
		'primary'     => '#1e61b9', // Apparatenman blauw
		'primary_dark'=> '#17509c',
		'dark'        => '#172635', // Donker marineblauw (titels)
		'accent'      => '#14b457', // Groen – primaire actieknop (Betalen)
		'accent_dark' => '#109a4a',
		'surface'     => '#f5f7f9', // Lichtgrijze achtergrond
		'border'      => '#e3e8ee',
		'text'        => '#1f2a37',
		'muted'       => '#5b6875',
		'radius'      => '12px',
		'font'        => '"Albert Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
		'logo'        => content_url( 'uploads/2025/08/Group-1990.svg' ),
	) );
}

/**
 * Logo-URL: gebruik het WordPress-sitelogo als dat is ingesteld, anders het
 * bekende Apparatenman-logo.
 */
function wtbp_logo_url() {
	$brand = wtbp_brand();
	$logo_id = get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		$src = wp_get_attachment_image_src( $logo_id, 'full' );
		if ( ! empty( $src[0] ) ) {
			return $src[0];
		}
	}
	return $brand['logo'];
}

/* =========================================================================
 * 1. GEEN INLOG / GEEN WACHTWOORD NODIG OM TE BETALEN
 * =========================================================================
 * Standaard vraagt WooCommerce de klant om in te loggen of zijn e-mail te
 * verifiëren wanneer de bestelling aan een geregistreerde klant hangt.
 * We geven de betaalrechten vrij, maar ALLEEN als de order-key in de URL
 * klopt (dat is de beveiliging die WooCommerce zelf ook gebruikt).
 * ========================================================================= */

// E-mailverificatie: alleen uit op de betaalpagina zelf (daar heeft WooCommerce de ordersleutel al
// gecontroleerd) en op de bedankpagina kort nadat de klant via de betaalpagina heeft afgerekend.
// Overal elders, en later via dezelfde link, geldt de gewone controle van WooCommerce weer, zodat
// naam, adres en telefoonnummer niet zichtbaar zijn voor iedereen die de link in handen krijgt.
add_filter( 'woocommerce_order_email_verification_required', 'wtbp_email_verification', 9999, 3 );
function wtbp_email_verification( $required, $order = null, $context = '' ) {
	if ( ! $required || ! $order instanceof WC_Order ) {
		return $required;
	}
	if ( '' === $context && function_exists( 'is_wc_endpoint_url' ) ) {
		$context = is_wc_endpoint_url( 'order-pay' ) ? 'order-pay' : ( is_wc_endpoint_url( 'order-received' ) ? 'order-received' : '' );
	}
	if ( 'order-pay' === $context ) {
		return false;
	}
	if ( 'order-received' === $context ) {
		$venster = (int) apply_filters( 'wtbp_verification_grace_seconds', 30 * MINUTE_IN_SECONDS, $order );
		$poging  = (int) $order->get_meta( '_wtbp_pay_attempt' );
		if ( $poging && ( time() - $poging ) <= $venster ) {
			return false;
		}
	}
	return $required;
}

// Betaalrechten toekennen bij een geldige order-key.
add_filter( 'user_has_cap', function ( $allcaps, $caps, $args ) {
	if ( isset( $caps[0], $_GET['key'] ) && 'pay_for_order' === $caps[0] ) {
		$order_id = isset( $args[2] ) ? absint( $args[2] ) : 0;
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( $order && hash_equals( (string) $order->get_order_key(), wc_clean( wp_unslash( $_GET['key'] ) ) ) ) {
			$allcaps['pay_for_order'] = true;
		}
	}
	return $allcaps;
}, 9999, 3 );

/* =========================================================================
 * 1a. HANDMATIGE BESTELLINGEN VERVALLEN NOOIT AUTOMATISCH
 * =========================================================================
 * WooCommerce annuleert onbetaalde bestellingen standaard na de "voorraad
 * vasthouden"-tijd (Instellingen → Producten → Voorraad). Voor bestellingen
 * die jij in het beheer aanmaakt is dat ongewenst: de betaallink moet
 * desnoods weken geldig blijven. Webshop-bestellingen van klanten houden het
 * normale gedrag, zodat voorraad daar niet eindeloos vastgehouden wordt.
 * Via de filter 'wtbp_never_expire' is dit gedrag aan te passen.
 * ========================================================================= */
add_filter( 'woocommerce_cancel_unpaid_order', function ( $cancel, $order ) {
	if ( ! $cancel || ! $order instanceof WC_Order ) {
		return $cancel;
	}

	// 'admin' = handmatig aangemaakt in het WooCommerce-beheer.
	$is_manual    = ( 'admin' === $order->get_created_via() );
	$never_expire = apply_filters( 'wtbp_never_expire', $is_manual, $order );

	return $never_expire ? false : $cancel;
}, 9999, 2 );

/*
 * Ook de Mollie-plugin (en eventuele andere systeemprocessen) kan een order
 * annuleren, bijvoorbeeld wanneer een gestarte iDEAL-/Bancontact-betaling
 * na een uur verloopt. Voor handmatig aangemaakte bestellingen draaien we
 * zo'n automatische annulering direct terug naar "In afwachting van
 * betaling", zodat de betaallink geldig blijft. Annuleert een beheerder de
 * order zelf (ingelogd, met winkelrechten), dan blijft die annulering
 * gewoon staan.
 */
add_action( 'woocommerce_order_status_changed', function ( $order_id, $from, $to, $order ) {
	if ( 'cancelled' !== $to || ! $order instanceof WC_Order ) {
		return;
	}
	if ( 'admin' !== $order->get_created_via() ) {
		return; // Alleen handmatig aangemaakte bestellingen beschermen.
	}
	if ( current_user_can( 'manage_woocommerce' ) ) {
		return; // Door een beheerder geannuleerd: bewust, laten staan.
	}
	$protect = apply_filters( 'wtbp_never_expire', true, $order );
	if ( ! $protect ) {
		return;
	}
	$order->add_order_note( __( 'Automatische annulering (bijv. door verlopen betaalpoging) ongedaan gemaakt door de betaalpagina-plugin. De bestelling blijft wachtend op betaling en de betaallink blijft geldig.', 'witgoed-betaalpagina' ) );
	$order->update_status( 'pending' );
}, 20, 4 );

/* =========================================================================
 * 1a-2. VRIENDELIJKE MELDING I.P.V. "JE BETAALT VOOR EEN GASTBESTELLING"
 * =========================================================================
 * WooCommerce toont op de betaalpagina bij gastbestellingen een rode
 * waarschuwing ("Je betaalt voor een gastbestelling. Ga alleen door ...").
 * Dat oogt als een foutmelding en wekt de indruk dat er een account nodig
 * is. Omdat jouw klanten de link rechtstreeks van jou krijgen, vervangen we
 * de tekst door een geruststellende welkomstboodschap; de styling wordt in
 * sectie 7 omgezet naar een rustige informatiebalk in huisstijl.
 * ========================================================================= */
add_filter( 'gettext', function ( $translation, $text, $domain ) {
	if ( 'woocommerce' === $domain && false !== strpos( $text, 'You are paying for a guest order' ) ) {
		if ( function_exists( 'is_wc_endpoint_url' ) && ! is_admin() ) {
			$translation = __( 'Welkom! Dit is jouw persoonlijke betaalpagina. Controleer hieronder je gegevens en rond de betaling veilig af.', 'witgoed-betaalpagina' );
		}
	}
	return $translation;
}, 10, 3 );

/* =========================================================================
 * 1b. HELPERS: VELDEN OPSCHONEN OP DE BETAALPAGINA
 * =========================================================================
 * Sommige plugins (o.a. PostNL/MyParcel) splitsen het adres in aparte velden
 * "Straat", "Nummer" en "Toevoeging" naast het standaardveld "Straat en
 * huisnummer". Dat oogt dubbel; we verbergen de splits-velden hier.
 * Daarnaast maken we het telefoonnummer verplicht.
 * Beide gedragingen zijn via filters aan te passen.
 * ========================================================================= */

/** Moet dit veld verborgen worden op de betaalpagina? */
function wtbp_is_hidden_field( $key ) {
	$patterns = apply_filters( 'wtbp_hidden_field_patterns', array( 'street_name', 'house_number' ) );
	foreach ( $patterns as $pattern ) {
		if ( false !== strpos( $key, $pattern ) ) {
			return true;
		}
	}
	return false;
}

/** Velden die op de betaalpagina altijd verplicht zijn. */
function wtbp_forced_required_fields() {
	return apply_filters( 'wtbp_required_fields', array( 'billing_phone' ) );
}

/* =========================================================================
 * 2. HELPER: HUIDIGE BESTELLING OP DE BETAALPAGINA OPHALEN
 * ========================================================================= */
function wtbp_get_current_pay_order() {
	global $wp;

	$order_id = 0;
	if ( isset( $wp->query_vars['order-pay'] ) && $wp->query_vars['order-pay'] ) {
		$order_id = absint( $wp->query_vars['order-pay'] );
	} elseif ( isset( $_GET['order-pay'] ) ) {
		$order_id = absint( $_GET['order-pay'] );
	}

	return $order_id ? wc_get_order( $order_id ) : false;
}

/* =========================================================================
 * 3. KOPBALK MET LOGO BOVEN DE BETAALPAGINA
 * ========================================================================= */
add_action( 'before_woocommerce_pay', 'wtbp_pay_header', 5 );
function wtbp_pay_header() {
	$logo = esc_url( wtbp_logo_url() );
	echo '<div class="wtbp-hero">';
	echo '<img class="wtbp-logo" src="' . $logo . '" alt="Apparatenman" />';
	echo '<h2 class="wtbp-hero-title">' . esc_html__( 'Je bestelling afronden', 'witgoed-betaalpagina' ) . '</h2>';
	echo '<p class="wtbp-hero-sub">' . esc_html__( 'Controleer je gegevens en reken veilig af.', 'witgoed-betaalpagina' ) . '</p>';
	echo '</div>';
}

/* =========================================================================
 * 4. BEWERKBARE FACTUUR- EN VERZENDVELDEN OP DE BETAALPAGINA
 * ========================================================================= */
add_action( 'woocommerce_pay_order_before_submit', 'wtbp_render_address_fields', 5 );
function wtbp_render_address_fields() {
	$order = wtbp_get_current_pay_order();
	if ( ! $order ) {
		return;
	}

	$checkout        = WC()->checkout();
	$billing_fields  = $checkout->get_checkout_fields( 'billing' );
	$shipping_fields = $checkout->get_checkout_fields( 'shipping' );

	$has_diff_shipping = $order->get_shipping_address_1() && (
		$order->get_shipping_address_1() !== $order->get_billing_address_1() ||
		$order->get_shipping_postcode() !== $order->get_billing_postcode()
	);

	// Na een validatiefout: wat de klant had ingevuld terugzetten (er is dan nog niets in de order opgeslagen).
	$eerder = get_transient( wtbp_posted_key( $order ) );
	$eerder = is_array( $eerder ) ? $eerder : array();
	if ( $eerder ) {
		$has_diff_shipping = ! empty( $eerder['wtbp_ship_to_different'] );
	}

	echo '<div class="wtbp-fields" id="wtbp-fields">';

	// ---- Factuurgegevens ----
	echo '<div class="wtbp-section">';
	echo '<h3 class="wtbp-title">' . esc_html__( 'Jouw factuurgegevens', 'witgoed-betaalpagina' ) . '</h3>';
	echo '<div class="wtbp-grid">';
	foreach ( $billing_fields as $key => $field ) {
		if ( wtbp_is_hidden_field( $key ) ) {
			continue; // Dubbele splits-velden (Straat/Nummer/Toevoeging) overslaan.
		}
		if ( in_array( $key, wtbp_forced_required_fields(), true ) ) {
			$field['required'] = true;
			$field['label']    = preg_replace( '/\s*\((optioneel|optional)\)\s*/i', '', (string) $field['label'] );
		}
		$getter = 'get_' . $key;
		$value  = is_callable( array( $order, $getter ) ) ? $order->$getter() : '';
		if ( array_key_exists( $key, $eerder ) ) {
			$value = $eerder[ $key ];
		}
		woocommerce_form_field( $key, $field, $value );
	}
	echo '</div>';
	echo '</div>';

	// ---- Toggle afwijkend verzendadres ----
	echo '<p class="form-row wtbp-toggle-row">';
	printf(
		'<label class="checkbox"><input type="checkbox" class="input-checkbox" name="wtbp_ship_to_different" id="wtbp_ship_to_different" value="1" %s /> <span>%s</span></label>',
		checked( $has_diff_shipping, true, false ),
		esc_html__( 'Verzenden naar een ander adres?', 'witgoed-betaalpagina' )
	);
	echo '</p>';

	// ---- Verzendgegevens ----
	echo '<div class="wtbp-section wtbp-shipping" id="wtbp-shipping" style="' . ( $has_diff_shipping ? '' : 'display:none;' ) . '">';
	echo '<h3 class="wtbp-title">' . esc_html__( 'Verzendadres', 'witgoed-betaalpagina' ) . '</h3>';
	echo '<div class="wtbp-grid">';
	foreach ( $shipping_fields as $key => $field ) {
		if ( wtbp_is_hidden_field( $key ) ) {
			continue;
		}
		$getter = 'get_' . $key;
		$value  = is_callable( array( $order, $getter ) ) ? $order->$getter() : '';
		if ( array_key_exists( $key, $eerder ) ) {
			$value = $eerder[ $key ];
		}
		if ( 'shipping_phone' === $key ) {
			$field['required'] = false; // Het telefoonnummer vragen we al bij de factuurgegevens.
		}
		// Het blok is ingeklapt zolang "ander adres" uit staat; daarom geen 'required' op het veld zelf.
		// Verplichte velden krijgen een sterretje en worden bij het opslaan gecontroleerd als het vinkje aan staat.
		if ( ! empty( $field['required'] ) ) {
			$field['class']   = isset( $field['class'] ) ? (array) $field['class'] : array();
			$field['class'][] = 'wtbp-req';
		}
		$field['required'] = false;
		woocommerce_form_field( $key, $field, $value );
	}
	echo '</div>';
	echo '</div>';

	// Geen eigen nonce: WooCommerce controleert vóór 'woocommerce_before_pay_action' zelf de nonce van het
	// betaalformulier en de ordersleutel. Dit veld zegt alleen dat onze adresvelden in het formulier stonden.
	echo '<input type="hidden" name="wtbp_fields_present" value="1" />';

	echo '</div>'; // .wtbp-fields
}

/* =========================================================================
 * 5. GEGEVENS OPSLAAN BIJ HET AFREKENEN
 * ========================================================================= */
add_action( 'woocommerce_before_pay_action', 'wtbp_save_address_fields', 10, 1 );

/** Sleutel waaronder de ingevulde gegevens na een fout kort bewaard worden. */
function wtbp_posted_key( $order ) {
	return 'wtbp_post_' . $order->get_id() . '_' . substr( md5( (string) $order->get_order_key() ), 0, 12 );
}

/** De adresvelden uit het formulier, opgeschoond. */
function wtbp_posted_values() {
	$checkout = WC()->checkout();
	$uit      = array( 'wtbp_ship_to_different' => empty( $_POST['wtbp_ship_to_different'] ) ? '' : '1' ); // phpcs:ignore WordPress.Security.NonceVerification
	foreach ( array( 'billing', 'shipping' ) as $soort ) {
		foreach ( array_keys( $checkout->get_checkout_fields( $soort ) ) as $key ) {
			if ( wtbp_is_hidden_field( $key ) ) {
				continue;
			}
			$uit[ $key ] = isset( $_POST[ $key ] ) ? wc_clean( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		}
	}
	return $uit;
}

/**
 * Controleert de ingevulde gegevens en zet ze op de order (zonder op te slaan).
 *
 * @return string[] Foutmeldingen; leeg als alles klopt.
 */
function wtbp_validate_and_apply( $order, array $posted ) {
	$checkout = WC()->checkout();
	$errors   = array();
	$landen   = array(
		'billing'  => array_keys( (array) WC()->countries->get_allowed_countries() ),
		'shipping' => array_keys( (array) WC()->countries->get_shipping_countries() ),
	);
	$anders   = ! empty( $posted['wtbp_ship_to_different'] );

	foreach ( array( 'billing', 'shipping' ) as $soort ) {
		if ( 'shipping' === $soort && ! $anders ) {
			continue;
		}
		$voor   = 'shipping' === $soort ? __( 'Verzendadres: ', 'witgoed-betaalpagina' ) : '';
		$land   = isset( $posted[ $soort . '_country' ] ) ? (string) $posted[ $soort . '_country' ] : '';
		foreach ( $checkout->get_checkout_fields( $soort ) as $key => $field ) {
			if ( wtbp_is_hidden_field( $key ) ) {
				continue; // Verborgen splits-velden niet valideren of overschrijven.
			}
			if ( in_array( $key, wtbp_forced_required_fields(), true ) ) {
				$field['required'] = true;
			}
			if ( 'shipping_phone' === $key ) {
				$field['required'] = false; // Het telefoonnummer vragen we al bij de factuurgegevens.
			}
			$waarde = isset( $posted[ $key ] ) ? (string) $posted[ $key ] : '';
			$label  = wp_strip_all_tags( isset( $field['label'] ) ? (string) $field['label'] : $key );

			if ( ! empty( $field['required'] ) && '' === $waarde ) {
				/* translators: %s: veldnaam */
				$errors[] = $voor . sprintf( __( '%s is een verplicht veld.', 'witgoed-betaalpagina' ), $label );
				continue;
			}
			if ( 'billing_email' === $key && '' !== $waarde && ! is_email( $waarde ) ) {
				$errors[] = __( 'Vul een geldig e-mailadres in.', 'witgoed-betaalpagina' );
				continue;
			}
			if ( $soort . '_country' === $key && '' !== $waarde && ! in_array( $waarde, $landen[ $soort ], true ) ) {
				$errors[] = $voor . ( 'shipping' === $soort
					? __( 'In dit land leveren wij niet. Kies een ander land of neem contact met ons op.', 'witgoed-betaalpagina' )
					: __( 'Dit land kun je hier niet kiezen. Neem contact met ons op.', 'witgoed-betaalpagina' ) );
				continue;
			}
			if ( $soort . '_postcode' === $key && '' !== $waarde ) {
				$waarde = wc_format_postcode( $waarde, $land );
				if ( '' !== $land && ! WC_Validation::is_postcode( $waarde, $land ) ) {
					$errors[] = $voor . __( 'Vul een geldige postcode in.', 'witgoed-betaalpagina' );
					continue;
				}
			}
			$setter = 'set_' . $key;
			if ( is_callable( array( $order, $setter ) ) ) {
				$order->$setter( $waarde );
			}
		}
	}

	if ( ! $anders ) {
		foreach ( array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' ) as $part ) {
			$b_getter = 'get_billing_' . $part;
			$s_setter = 'set_shipping_' . $part;
			if ( is_callable( array( $order, $b_getter ) ) && is_callable( array( $order, $s_setter ) ) ) {
				$order->$s_setter( $order->$b_getter() );
			}
		}
		// Factuuradres wordt ook het afleveradres: dan moet er in dat land geleverd worden.
		$b_land = (string) $order->get_billing_country();
		if ( '' !== $b_land && ! in_array( $b_land, $landen['shipping'], true ) ) {
			$errors[] = __( 'In dit land leveren wij niet. Vul een verzendadres in een ander land in of neem contact met ons op.', 'witgoed-betaalpagina' );
		}
	}

	return array_values( array_unique( $errors ) );
}

function wtbp_save_address_fields( $order ) {
	// WooCommerce heeft hier de nonce van het betaalformulier en de ordersleutel al gecontroleerd.
	if ( empty( $_POST['wtbp_fields_present'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return; // Een betaalformulier zonder onze adresvelden: niets te doen.
	}

	$posted = wtbp_posted_values();
	$errors = wtbp_validate_and_apply( $order, $posted );

	// ---- Fouten? Niets opslaan, niet betalen: terug naar de betaalpagina met de melding en de ingevulde gegevens. ----
	if ( ! empty( $errors ) ) {
		set_transient( wtbp_posted_key( $order ), $posted, 15 * MINUTE_IN_SECONDS );
		foreach ( $errors as $msg ) {
			wc_add_notice( $msg, 'error' );
		}
		wp_safe_redirect( $order->get_checkout_payment_url() );
		exit;
	}

	delete_transient( wtbp_posted_key( $order ) );
	$order->update_meta_data( '_wtbp_pay_attempt', time() );
	$order->save();
}

/* =========================================================================
 * 6. BEDANKPAGINA IN HUISSTIJL (geldt voor ÁLLE bestellingen)
 * =========================================================================
 * De WooCommerce "Bestelling ontvangen"-pagina is waar élke betaling na
 * afloop op uitkomt – ook je normale webshop-checkout en andere gateways.
 * We voegen bovenaan een merk-hero toe en daaronder een "Wat gebeurt er nu?"-
 * blok. Het standaard-besteloverzicht blijft gewoon staan.
 * ========================================================================= */

/**
 * In welke toestand is de bestelling voor de klant?
 * betaald | wacht (on-hold) | open (nog niet betaald) | mislukt | geannuleerd | overig
 */
function wtbp_order_state( $order ) {
	if ( $order->has_status( 'failed' ) ) {
		return 'mislukt';
	}
	if ( $order->has_status( 'cancelled' ) ) {
		return 'geannuleerd';
	}
	if ( $order->has_status( 'pending' ) ) {
		return 'open';
	}
	if ( $order->has_status( 'on-hold' ) ) {
		return 'wacht';
	}
	if ( $order->is_paid() ) {
		return 'betaald';
	}
	return 'overig';
}

// Hero bovenaan de bedankpagina.
add_action( 'woocommerce_before_thankyou', 'wtbp_thankyou_hero', 5 );
function wtbp_thankyou_hero( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	$name   = trim( $order->get_billing_first_name() );
	$logo   = esc_url( wtbp_logo_url() );
	$state  = wtbp_order_state( $order );
	$nummer = '<strong>#' . esc_html( $order->get_order_number() ) . '</strong>';
	$dank   = $name
		? sprintf( __( 'Bedankt voor je bestelling, %s!', 'witgoed-betaalpagina' ), $name )
		: __( 'Bedankt voor je bestelling!', 'witgoed-betaalpagina' );
	$knop   = '';

	switch ( $state ) {
		case 'mislukt':
			$icoon = array( 'is-fail', '&#10005;' );
			$titel = __( 'De betaling is niet gelukt', 'witgoed-betaalpagina' );
			$tekst = esc_html__( 'Er is iets misgegaan met je betaling. Je kunt het hieronder opnieuw proberen.', 'witgoed-betaalpagina' );
			break;
		case 'geannuleerd':
			$icoon = array( 'is-fail', '&#10005;' );
			$titel = __( 'Deze bestelling is geannuleerd', 'witgoed-betaalpagina' );
			/* translators: %s: order number */
			$tekst = sprintf( esc_html__( 'Bestelling %s is geannuleerd. Neem contact met ons op als dat niet klopt of als je alsnog wilt bestellen.', 'witgoed-betaalpagina' ), $nummer );
			break;
		case 'open':
			$icoon = array( 'is-wait', '&#8230;' );
			$titel = __( 'We wachten nog op je betaling', 'witgoed-betaalpagina' );
			/* translators: %s: order number */
			$tekst = sprintf( esc_html__( 'Bestelling %s is nog niet betaald. Is de betaling afgebroken? Dan kun je hem opnieuw starten.', 'witgoed-betaalpagina' ), $nummer );
			if ( $order->needs_payment() ) {
				$knop = '<p class="wtbp-thanks-actie"><a class="wtbp-knop" href="' . esc_url( $order->get_checkout_payment_url() ) . '">' . esc_html__( 'Betaling opnieuw starten', 'witgoed-betaalpagina' ) . '</a></p>';
			}
			break;
		case 'wacht':
			$icoon = array( 'is-wait', '&#8230;' );
			$titel = $dank;
			/* translators: %s: order number */
			$tekst = sprintf( esc_html__( 'Je bestelling %s is ontvangen. We verwerken hem zodra de betaling bij ons binnen is.', 'witgoed-betaalpagina' ), $nummer );
			break;
		case 'betaald':
			$icoon = array( '', '&#10003;' );
			$titel = $dank;
			/* translators: %s: order number */
			$tekst = sprintf( esc_html__( 'Je bestelling %s is ontvangen en wordt verwerkt.', 'witgoed-betaalpagina' ), $nummer );
			break;
		default:
			$icoon = array( 'is-wait', '&#8230;' );
			/* translators: %s: order number */
			$titel = sprintf( __( 'Bestelling #%s', 'witgoed-betaalpagina' ), $order->get_order_number() );
			$tekst = esc_html__( 'Hieronder vind je de gegevens van je bestelling.', 'witgoed-betaalpagina' );
	}

	echo '<div class="wtbp-thanks-hero wtbp-state-' . esc_attr( $state ) . '">';
	echo '<img class="wtbp-logo" src="' . $logo . '" alt="Apparatenman" />';
	echo '<div class="wtbp-check ' . esc_attr( $icoon[0] ) . '" aria-hidden="true">' . $icoon[1] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<h1 class="wtbp-thanks-title">' . esc_html( $titel ) . '</h1>';
	echo '<p class="wtbp-thanks-sub">' . $tekst . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput — opgebouwd met esc_*.
	echo $knop; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';
}

// "Wat gebeurt er nu?" – vóór het standaard-besteloverzicht (dat op prio 10 draait).
add_action( 'woocommerce_thankyou', 'wtbp_thankyou_steps', 5 );
function wtbp_thankyou_steps( $order_id ) {
	$order = wc_get_order( $order_id );
	// Alleen bij een betaalde bestelling of een bestelling die op de betaling wacht (bijv. overschrijving).
	if ( ! $order || ! in_array( wtbp_order_state( $order ), array( 'betaald', 'wacht' ), true ) ) {
		return;
	}

	$steps = apply_filters( 'wtbp_thankyou_steps', array(
		array(
			'title' => __( 'Bevestiging per e-mail', 'witgoed-betaalpagina' ),
			'text'  => __( 'Je ontvangt binnen enkele minuten een bevestiging met de factuur op het opgegeven e-mailadres.', 'witgoed-betaalpagina' ),
		),
		array(
			'title' => __( 'Wij verwerken je bestelling', 'witgoed-betaalpagina' ),
			'text'  => __( 'We maken je bestelling klaar en nemen contact met je op om de levering of het afhalen in te plannen.', 'witgoed-betaalpagina' ),
		),
		array(
			'title' => __( 'Vragen?', 'witgoed-betaalpagina' ),
			'text'  => __( 'Neem gerust contact met ons op als er iets niet klopt of als je iets wilt wijzigen.', 'witgoed-betaalpagina' ),
		),
	), $order );

	echo '<div class="wtbp-section wtbp-steps-wrap">';
	echo '<h2 class="wtbp-title">' . esc_html__( 'Wat gebeurt er nu?', 'witgoed-betaalpagina' ) . '</h2>';
	echo '<ol class="wtbp-steps">';
	$i = 1;
	foreach ( $steps as $step ) {
		echo '<li class="wtbp-step">';
		echo '<span class="wtbp-step-num">' . intval( $i ) . '</span>';
		echo '<span class="wtbp-step-body"><strong>' . esc_html( $step['title'] ) . '</strong>' . esc_html( $step['text'] ) . '</span>';
		echo '</li>';
		$i++;
	}
	echo '</ol>';
	echo '</div>';
}

/* =========================================================================
 * 7. STYLING (huisstijl) + KLEINE JS
 * =========================================================================
 * Laadt alleen op de betaalpagina (order-pay) en de bedankpagina
 * (order-received). Alle kleuren komen uit wtbp_brand().
 * ========================================================================= */
add_action( 'wp_enqueue_scripts', 'wtbp_assets', 20 );
function wtbp_assets() {
	if ( ! function_exists( 'is_wc_endpoint_url' ) ) {
		return;
	}
	$is_pay      = is_wc_endpoint_url( 'order-pay' );
	$is_received = is_wc_endpoint_url( 'order-received' );
	if ( ! $is_pay && ! $is_received ) {
		return;
	}

	$b = wtbp_brand();

	// Het thema laadt Albert Sans al zelf. Alleen op een site waar dat niet zo is, kan de plugin het
	// lettertype van Google Fonts laden: add_filter( 'wtbp_load_font', '__return_true' );
	if ( apply_filters( 'wtbp_load_font', false ) ) {
		wp_enqueue_style( 'wtbp-font', 'https://fonts.googleapis.com/css2?family=Albert+Sans:wght@400;500;600;700&display=swap', array(), null );
	}

	$css = '
	:root{
		--wtbp-primary:' . $b['primary'] . ';
		--wtbp-primary-dark:' . $b['primary_dark'] . ';
		--wtbp-dark:' . $b['dark'] . ';
		--wtbp-accent:' . $b['accent'] . ';
		--wtbp-accent-dark:' . $b['accent_dark'] . ';
		--wtbp-surface:' . $b['surface'] . ';
		--wtbp-border:' . $b['border'] . ';
		--wtbp-text:' . $b['text'] . ';
		--wtbp-muted:' . $b['muted'] . ';
		--wtbp-radius:' . $b['radius'] . ';
		--wtbp-font:' . $b['font'] . ';
	}
	.woocommerce-order-pay .site-content,
	.woocommerce-order-received .site-content,
	.woocommerce-order-pay .entry-content,
	.woocommerce-order-received .entry-content{max-width:720px;margin-left:auto;margin-right:auto;}

	.wtbp-hero,.wtbp-thanks-hero{text-align:center;font-family:var(--wtbp-font);padding:8px 0 22px;}
	.wtbp-logo{height:44px;width:auto;margin:0 auto 18px;display:block;}
	.wtbp-hero-title,.wtbp-thanks-title{font-family:var(--wtbp-font);color:var(--wtbp-dark);font-weight:700;margin:0 0 6px;line-height:1.2;}
	.wtbp-hero-title{font-size:1.5em;}
	.wtbp-thanks-title{font-size:1.7em;}
	.wtbp-hero-sub,.wtbp-thanks-sub{color:var(--wtbp-muted);margin:0;font-size:1.02em;}
	.wtbp-check{width:64px;height:64px;line-height:64px;border-radius:50%;background:var(--wtbp-accent);color:#fff;font-size:34px;margin:0 auto 16px;box-shadow:0 6px 18px rgba(20,180,87,.35);}

	.woocommerce-order-pay .wtbp-fields,
	.woocommerce-order-pay .wtbp-section,
	.woocommerce-order-received .wtbp-section{font-family:var(--wtbp-font);color:var(--wtbp-text);}
	.wtbp-section{background:#fff;border:1px solid var(--wtbp-border);border-radius:var(--wtbp-radius);padding:22px 24px;margin:0 0 16px;box-shadow:0 1px 2px rgba(23,38,53,.04);}
	.wtbp-title{font-family:var(--wtbp-font);color:var(--wtbp-dark);font-weight:600;font-size:1.1em;margin:0 0 16px;}

	.wtbp-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px;}
	.wtbp-grid .form-row{float:none!important;width:auto!important;margin:0 0 14px;padding:0;}
	.wtbp-grid #billing_email_field,.wtbp-grid #billing_phone_field,.wtbp-grid #billing_company_field,
	.wtbp-grid #billing_address_1_field,.wtbp-grid #billing_address_2_field,
	.wtbp-grid #shipping_company_field,.wtbp-grid #shipping_address_1_field,.wtbp-grid #shipping_address_2_field,
	.wtbp-grid .form-row-wide{grid-column:1 / -1;}
	.wtbp-grid label{display:block;font-weight:500;color:var(--wtbp-dark);margin-bottom:5px;font-size:.95em;}
	.wtbp-grid .required{color:var(--wtbp-accent);border:0;}
	.wtbp-grid input.input-text,.wtbp-grid select,.wtbp-grid .select2-container{width:100%!important;box-sizing:border-box;}
	.wtbp-grid input.input-text{padding:11px 13px;border:1px solid #cbd5e1;border-radius:10px;font-family:var(--wtbp-font);font-size:1em;background:#fff;transition:border-color .15s,box-shadow .15s;}
	.wtbp-grid input.input-text:focus{border-color:var(--wtbp-primary);box-shadow:0 0 0 3px rgba(30,97,185,.15);outline:none;}
	.select2-container--default .select2-selection--single{border:1px solid #cbd5e1!important;border-radius:10px!important;height:44px!important;padding:5px 6px;}

	/* Vriendelijke informatiebalk (vervangt de rode gastbestelling-melding) */
	.wtbp-note{background:#eaf2fd!important;border:1px solid var(--wtbp-primary)!important;color:var(--wtbp-dark)!important;border-radius:var(--wtbp-radius)!important;padding:14px 18px!important;margin:0 0 16px!important;font-family:var(--wtbp-font)!important;list-style:none!important;}
	.wtbp-note li{list-style:none!important;margin:0!important;padding:0!important;color:var(--wtbp-dark)!important;}
	.wtbp-note::before,.wtbp-note li::before{display:none!important;content:none!important;}

	.wtbp-toggle-row{margin:2px 2px 18px!important;}
	.wtbp-toggle-row label{cursor:pointer;font-weight:500;color:var(--wtbp-dark);display:inline-flex;align-items:center;gap:8px;}

	/* Betaalmethoden-blok */
	.woocommerce-order-pay ul.wc_payment_methods{border:1px solid var(--wtbp-border);border-radius:var(--wtbp-radius);background:#fff;padding:6px 18px;margin:0 0 16px;}
	.woocommerce-order-pay .payment_box{background:var(--wtbp-surface)!important;border-radius:10px;}
	.woocommerce-order-pay .payment_box::before{border-bottom-color:var(--wtbp-surface)!important;}

	/* Primaire actieknop (Betalen) in merkgroen */
	body.woocommerce-order-pay #payment #place_order,
	body.woocommerce-order-pay.woocommerce-page #payment #place_order,
	body.woocommerce-order-pay .woocommerce #place_order,
	body.woocommerce-order-pay button.button.alt,
	body.woocommerce-order-received .woocommerce-button.button,
	.wtbp-knop{
		background:var(--wtbp-accent)!important;border:0!important;color:#fff!important;
		font-family:var(--wtbp-font)!important;font-weight:600!important;font-size:1.05em!important;
		padding:14px 26px!important;border-radius:10px!important;width:100%;box-shadow:0 4px 14px rgba(20,180,87,.30);
		transition:background .15s,transform .05s;cursor:pointer;}
	body.woocommerce-order-pay #payment #place_order:hover,
	body.woocommerce-order-pay.woocommerce-page #payment #place_order:hover,
	body.woocommerce-order-pay button.button.alt:hover,
	.wtbp-knop:hover{background:var(--wtbp-accent-dark)!important;}
	.wtbp-knop{display:inline-block;width:auto;text-decoration:none!important;}
	.wtbp-thanks-actie{margin:18px 0 0;}
	.wtbp-check.is-fail{background:#c0392b;box-shadow:0 6px 18px rgba(192,57,43,.30);}
	.wtbp-check.is-wait{background:var(--wtbp-primary);box-shadow:0 6px 18px rgba(30,97,185,.30);}
	/* Verplichte velden van het verzendadres: sterretje in plaats van "(optioneel)". */
	.wtbp-req .optional{display:none;}
	.wtbp-req > label::after{content:" *";color:var(--wtbp-accent);}
	/* Het thema zet de inhoud van de bedankpagina in een grid van 12 kolommen: ons blok over de volle breedte. */
	.wc-order-details > .wtbp-steps-wrap{grid-column:1 / -1;width:100%;min-width:0;}
	.woocommerce-order-pay #payment #place_order:active{transform:translateY(1px);}

	/* Besteloverzicht-tabel netjes */
	.woocommerce-order-pay .shop_table,
	.woocommerce-order-received .woocommerce-table--order-details,
	.woocommerce-order-received .woocommerce-order-overview{font-family:var(--wtbp-font);border-radius:var(--wtbp-radius);overflow:hidden;}
	.woocommerce-order-received ul.woocommerce-order-overview{border:1px solid var(--wtbp-border);border-radius:var(--wtbp-radius);background:#fff;padding:18px 22px;}
	.woocommerce-order-received .woocommerce-order-overview strong{color:var(--wtbp-dark);}

	/* Wat gebeurt er nu? */
	.wtbp-steps{list-style:none;margin:0;padding:0;counter-reset:none;}
	.wtbp-step{display:flex;gap:14px;align-items:flex-start;padding:12px 0;border-bottom:1px solid var(--wtbp-border);}
	.wtbp-step:last-child{border-bottom:0;}
	.wtbp-step-num{flex:0 0 auto;width:28px;height:28px;line-height:28px;text-align:center;border-radius:50%;background:var(--wtbp-primary);color:#fff;font-weight:600;font-size:.9em;}
	.wtbp-step-body{color:var(--wtbp-text);}
	.wtbp-step-body strong{display:block;color:var(--wtbp-dark);margin-bottom:2px;}

	@media(max-width:600px){
		.wtbp-grid{grid-template-columns:1fr;}
		.wtbp-thanks-title{font-size:1.4em;}
	}
	';

	wp_register_style( 'wtbp-inline', false );
	wp_enqueue_style( 'wtbp-inline' );
	wp_add_inline_style( 'wtbp-inline', $css );

	// JS alleen op de betaalpagina (velden verplaatsen + verzendadres in-/uitklappen).
	if ( $is_pay ) {
		$js = "
		(function(){
			function ready(fn){ if(document.readyState!='loading'){fn();}else{document.addEventListener('DOMContentLoaded',fn);} }
			ready(function(){
				// Gastbestelling-melding omzetten naar rustige informatiebalk.
				document.querySelectorAll('.woocommerce-error, .woocommerce-info, .woocommerce-message, .wc-block-components-notice-banner').forEach(function(box){
					var t=(box.textContent||'');
					if(t.indexOf('persoonlijke betaalpagina')!==-1||t.indexOf('gastbestelling')!==-1){
						box.className='wtbp-note';
					}
				});
				var fields=document.getElementById('wtbp-fields');
				if(fields){
					var methods=document.querySelector('.wc_payment_methods, #payment .payment_methods');
					if(methods&&methods.parentNode){methods.parentNode.insertBefore(fields,methods);}
					var cb=document.getElementById('wtbp_ship_to_different');
					var ship=document.getElementById('wtbp-shipping');
					if(cb&&ship){cb.addEventListener('change',function(){ship.style.display=cb.checked?'':'none';});}
				}
			});
		})();
		";
		wp_add_inline_script( 'jquery-core', $js );
	}
}

/* =========================================================================
 * 7b. GOOGLE CONSENT MODE-KOPPELING (Complianz → Site Kit / gtag)
 * =========================================================================
 * Site Kit zet Google Consent Mode standaard op "denied". Complianz toont de
 * cookiebanner, maar zonder de (verwijderde) WP Consent API-plugin of
 * Complianz Premium komt de keuze van de bezoeker nooit bij Google terecht.
 * Dit script luistert naar Complianz en stuurt gtag('consent','update', ...)
 * zodra de bezoeker accepteert, weigert of zijn voorkeuren wijzigt.
 *
 * Mapping (Complianz-categorie → Consent Mode v2):
 *   statistics  → analytics_storage
 *   marketing   → ad_storage, ad_user_data, ad_personalization
 *   preferences → functionality_storage, personalization_storage
 * Uitzetten kan met: add_filter( 'wtbp_consent_bridge', '__return_false' );
 * ========================================================================= */
add_action( 'wp_head', 'wtbp_consent_bridge', 5 );
function wtbp_consent_bridge() {
	// Complianz Premium (sinds 7 okt 2026 actief) stuurt de toestemming zelf naar de
	// Google-tag; dan is deze koppeling overbodig en zou hij dubbel sturen.
	$standaard = ! defined( 'cmplz_premium' );
	if ( ! apply_filters( 'wtbp_consent_bridge', $standaard ) ) {
		return;
	}
	?>
<script id="wtbp-consent-bridge">
(function(){
	window.dataLayer = window.dataLayer || [];
	function gtagc(){ if (typeof window.gtag === 'function') { window.gtag.apply(null, arguments); } else { window.dataLayer.push(arguments); } }
	var last = '';
	function update(cats){
		cats = Array.isArray(cats) ? cats : [];
		var stats = cats.indexOf('statistics') !== -1;
		var mkt   = cats.indexOf('marketing') !== -1;
		var pref  = cats.indexOf('preferences') !== -1 || cats.indexOf('functional') !== -1;
		var state = {
			analytics_storage:       stats ? 'granted' : 'denied',
			ad_storage:              mkt   ? 'granted' : 'denied',
			ad_user_data:            mkt   ? 'granted' : 'denied',
			ad_personalization:      mkt   ? 'granted' : 'denied',
			functionality_storage:   pref  ? 'granted' : 'denied',
			personalization_storage: pref  ? 'granted' : 'denied',
			security_storage:        'granted'
		};
		var key = JSON.stringify(state);
		if (key === last) { return; }
		last = key;
		gtagc('consent', 'update', state);
		try { window.dataLayer.push({ event: 'wtbp_consent_update', wtbp_consent: state }); } catch (e) {}
	}
	// Complianz vuurt dit bij laden (met de huidige toestemming) en na elke wijziging.
	document.addEventListener('cmplz_fire_categories', function(e){
		if (e && e.detail && e.detail.categories) { update(e.detail.categories); }
	});
	// Vangnet: als de listener te laat kwam, lees de huidige status uit Complianz.
	function fallback(){
		if (typeof window.cmplz_has_consent !== 'function') { return; }
		var cats = ['functional'];
		['preferences','statistics','marketing'].forEach(function(c){ try { if (window.cmplz_has_consent(c)) { cats.push(c); } } catch (e) {} });
		if (cats.length > 1) { update(cats); }
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function(){ setTimeout(fallback, 1500); }); } else { setTimeout(fallback, 1500); }
})();
</script>
	<?php
}

/* =========================================================================
 * 7c. GOOGLE ANALYTICS "purchase"-EVENT (Site Kit) OP DIT THEMA
 * =========================================================================
 * Site Kit (Plugin conversion tracking) hangt op 'woocommerce_thankyou' een
 * inline script met de bestelgegevens aan zijn script-handle
 * 'googlesitekit-events-provider-woocommerce'. Die handle wordt pas bij
 * 'wp_enqueue_scripts' (in wp_head) geregistreerd. Het apparatenman-thema
 * (Sage/Blade) rendert de pagina-inhoud – en dus de bedankpagina – vóórdat
 * wp_head() draait. Gevolg: wp_add_inline_script() faalt stilletjes (handle
 * bestaat nog niet), terwijl Site Kit de order wél al als
 * '_googlesitekit_ga_purchase_event_tracked' markeert. Er komt dus nooit een
 * purchase-event in GA4 en dus ook geen conversie in Google Ads.
 *
 * Oplossing: wij verzamelen op 'woocommerce_thankyou' (prioriteit 11, ná Site
 * Kit) dezelfde bestelgegevens en sturen het event vlak vóór het printen van
 * de footer-scripts zelf via gtag(). Eén keer per bestelling (eigen meta),
 * zelfde opbouw als Site Kit (transaction_id, value, currency, shipping, tax,
 * items). Staat op dat moment het aankoop-script van Site Kit wél klaar (thema
 * of Site Kit gerepareerd), dan doen wij niets – geen dubbele conversies.
 * Uitzetten kan met: add_filter( 'wtbp_sitekit_purchase_fix', '__return_false' );
 * ========================================================================= */
add_action( 'woocommerce_thankyou', 'wtbp_sitekit_purchase_fix', 11 );
function wtbp_sitekit_purchase_fix( $order_id ) {
	if ( ! apply_filters( 'wtbp_sitekit_purchase_fix', true ) ) {
		return;
	}
	$order = $order_id ? wc_get_order( $order_id ) : false;
	// Alleen een betaalde bestelling is een aankoop; een afgebroken of open betaling telt niet als conversie.
	if ( ! $order || ! $order->is_paid() ) {
		return;
	}
	// Zelfde beveiliging als Site Kit/WooCommerce: alleen met geldige ordersleutel.
	$key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $key || ! $order->key_is_valid( $key ) ) {
		return;
	}
	if ( '1' === (string) $order->get_meta( '_wtbp_ga_purchase_sent' ) ) {
		return;
	}
	$order->update_meta_data( '_wtbp_ga_purchase_sent', 1 );
	$order->save();

	$items = array();
	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();
		$qty     = max( 1, (int) $item->get_quantity() );
		$row     = array(
			'item_id'   => $product ? ( $product->get_sku() ? $product->get_sku() : (string) $product->get_id() ) : (string) $item->get_product_id(),
			'item_name' => $item->get_name(),
			'price'     => round( (float) $order->get_item_total( $item, false ), 2 ),
			'quantity'  => $qty,
		);
		if ( $product && $product->is_type( 'variation' ) ) {
			$row['item_variant'] = wc_get_formatted_variation( $product, true, false, false );
		}
		if ( $product ) {
			$cats = wc_get_product_category_list( $product->get_parent_id() ? $product->get_parent_id() : $product->get_id(), '|' );
			$cats = array_values( array_filter( array_map( 'trim', explode( '|', wp_strip_all_tags( $cats ) ) ) ) );
			$n    = 1;
			foreach ( array_slice( $cats, 0, 5 ) as $cat ) {
				$row[ 1 === $n ? 'item_category' : 'item_category' . $n ] = $cat;
				$n++;
			}
		}
		$items[] = $row;
	}
	$payload = array(
		'transaction_id' => $order->get_order_number(),
		'value'          => round( (float) $order->get_total(), 2 ),
		'currency'       => $order->get_currency(),
		'shipping'       => round( (float) $order->get_shipping_total(), 2 ),
		'tax'            => round( (float) $order->get_total_tax(), 2 ),
		'items'          => $items,
		'event_source'   => 'wtbp',
	);
	$GLOBALS['wtbp_purchase_payload'] = $payload;
	// Prioriteit 19: ná Site Kit's eigen footer-callback (10) en vóór het printen
	// van de footer-scripts (20), zodat we kunnen zien of Site Kit het zelf doet.
	add_action( 'wp_footer', 'wtbp_sitekit_purchase_print', 19 );
}

function wtbp_sitekit_purchase_print() {
	if ( empty( $GLOBALS['wtbp_purchase_payload'] ) ) {
		return;
	}
	// Staat het aankoop-script van Site Kit wél klaar om geprint te worden? Dan
	// doet Site Kit het goed en blijven wij weg (geen dubbele conversie).
	$before = wp_scripts()->get_data( 'googlesitekit-events-provider-woocommerce', 'before' );
	if ( is_array( $before ) && false !== strpos( implode( ' ', array_filter( $before, 'is_string' ) ), 'wcdata.purchase' ) ) {
		return;
	}
	$json = wp_json_encode( $GLOBALS['wtbp_purchase_payload'] );
	?>
<script id="wtbp-ga-purchase">
(function(){
	window.dataLayer = window.dataLayer || [];
	function gtag(){ window.dataLayer.push(arguments); }
	gtag('event', 'purchase', <?php echo $json; // phpcs:ignore WordPress.Security.EscapeOutput ?>);
})();
</script>
	<?php
}

/* =========================================================================
 * 7b. PRODUCTPAGINA: LEVERTIJD, TELEFOON/WHATSAPP, BETAALMETHODEN (1.5.0)
 * =========================================================================
 * Het thema (Sage/Blade) gebruikt eigen templates; WooCommerce-hooks op de
 * productpagina lopen daar niet betrouwbaar. Daarom zetten we de blokken met
 * een klein script in de pagina (na de voorraadregel en onder de koopknop),
 * en gebruiken we de hooks alleen als extra vangnet (dubbel wordt voorkomen).
 *
 * Levertijd komt uit AM Coeur (leverbelofte-engine) via
 *   GET {AM Coeur-URL}/api/wgt/levertijd?woo_id=…&sku=…&ean=…
 *   Authorization: Bearer {inkomend token van de webshop}
 * en wordt per product 4 uur in een transient bewaard.
 *
 * Instellen: WooCommerce → Productpagina (Apparatenman). Alles kan ook via
 * wp-config.php: define('WTBP_AMCOEUR_URL', '…'); define('WTBP_AMCOEUR_TOKEN', '…').
 * ========================================================================= */
const WTBP_PP_OPTION = 'wtbp_productpagina';
const WTBP_PP_TOKEN  = 'wtbp_amcoeur_token'; // eigen optie, niet automatisch geladen (autoload uit)

function wtbp_pp_defaults() {
	return array(
		'amcoeur_url'     => defined( 'WTBP_AMCOEUR_URL' ) ? WTBP_AMCOEUR_URL : '',
		'amcoeur_token'   => defined( 'WTBP_AMCOEUR_TOKEN' ) ? WTBP_AMCOEUR_TOKEN : '',
		'levertijd_aan'   => '1',
		'cache_minuten'   => '240',
		'telefoon'        => '072 - 202 91 62',
		'whatsapp'        => '',
		'openingstijden'  => 'ma t/m vr 9:00 - 17:00',
		'betaalmethoden'  => '', // leeg = automatisch uit de actieve betaalmethoden van WooCommerce
		'review_url'      => '',
		'review_tekst'    => 'Lees de ervaringen van onze klanten',
		'blok_aan'        => '1',
	);
}

function wtbp_pp_opties() {
	$opt = get_option( WTBP_PP_OPTION, array() );
	if ( ! is_array( $opt ) ) {
		$opt = array();
	}
	// Het token staat in een eigen optie zonder autoload. Staat het nog in de oude, automatisch geladen
	// optie (versie 1.5.0), dan verhuist het hier één keer.
	$token = (string) get_option( WTBP_PP_TOKEN, '' );
	if ( '' === $token && ! empty( $opt['amcoeur_token'] ) ) {
		$token = (string) $opt['amcoeur_token'];
		update_option( WTBP_PP_TOKEN, $token, false );
	}
	if ( array_key_exists( 'amcoeur_token', $opt ) ) {
		unset( $opt['amcoeur_token'] );
		update_option( WTBP_PP_OPTION, $opt );
	}
	$opt                  = wp_parse_args( $opt, wtbp_pp_defaults() );
	$opt['amcoeur_token'] = $token;
	// Het veiligst is een constante in wp-config.php of de omgeving; die wint altijd van de database.
	if ( defined( 'WTBP_AMCOEUR_URL' ) && WTBP_AMCOEUR_URL ) {
		$opt['amcoeur_url'] = WTBP_AMCOEUR_URL;
	}
	if ( defined( 'WTBP_AMCOEUR_TOKEN' ) && WTBP_AMCOEUR_TOKEN ) {
		$opt['amcoeur_token'] = WTBP_AMCOEUR_TOKEN;
	}
	return apply_filters( 'wtbp_pp_opties', $opt );
}

/* ---- Instellingenpagina (WooCommerce → Productpagina) -------------------- */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'woocommerce',
		'Productpagina (Apparatenman)',
		'Productpagina',
		'manage_woocommerce',
		'wtbp-productpagina',
		'wtbp_pp_settings_page'
	);
} );

add_action( 'admin_init', function () {
	register_setting( 'wtbp_pp', WTBP_PP_OPTION, array(
		'type'              => 'array',
		'sanitize_callback' => 'wtbp_pp_sanitize',
	) );
} );

function wtbp_pp_sanitize( $in ) {
	$d   = wtbp_pp_defaults();
	$out = array();
	$in  = is_array( $in ) ? $in : array();
	$out['amcoeur_url']    = esc_url_raw( untrailingslashit( trim( (string) ( $in['amcoeur_url'] ?? '' ) ) ) );
	// Het token gaat naar een eigen optie zonder autoload en komt niet in deze instellingen-array.
	// Leeg gelaten = oude token houden (het veld toont het token nooit terug).
	$token = trim( (string) ( $in['amcoeur_token'] ?? '' ) );
	if ( '__wis__' === $token ) {
		delete_option( WTBP_PP_TOKEN );
	} elseif ( '' !== $token ) {
		update_option( WTBP_PP_TOKEN, sanitize_text_field( $token ), false );
	}
	$out['levertijd_aan']  = empty( $in['levertijd_aan'] ) ? '0' : '1';
	$out['blok_aan']       = empty( $in['blok_aan'] ) ? '0' : '1';
	$cache                 = (int) ( $in['cache_minuten'] ?? $d['cache_minuten'] );
	$out['cache_minuten']  = (string) max( 5, min( 1440, $cache ?: 240 ) );
	$out['telefoon']       = sanitize_text_field( $in['telefoon'] ?? $d['telefoon'] );
	$out['whatsapp']       = preg_replace( '/[^0-9+]/', '', (string) ( $in['whatsapp'] ?? '' ) );
	$out['openingstijden'] = sanitize_text_field( $in['openingstijden'] ?? $d['openingstijden'] );
	$out['betaalmethoden'] = sanitize_text_field( $in['betaalmethoden'] ?? '' );
	$out['review_url']     = esc_url_raw( trim( (string) ( $in['review_url'] ?? '' ) ) );
	$out['review_tekst']   = sanitize_text_field( $in['review_tekst'] ?? $d['review_tekst'] );
	// Cache van levertijden legen zodat nieuwe instellingen direct zichtbaar zijn.
	wtbp_pp_wis_cache();
	return $out;
}

function wtbp_pp_wis_cache() {
	global $wpdb;
	// Transients van deze plugin (per product) in één keer weg.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_wtbp\_lt\_%' OR option_name LIKE '\_transient\_timeout\_wtbp\_lt\_%'" ); // phpcs:ignore
	delete_transient( 'wtbp_pp_betaalmethoden' );
}

function wtbp_pp_settings_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$o        = wtbp_pp_opties();
	$vast_url = defined( 'WTBP_AMCOEUR_URL' ) && WTBP_AMCOEUR_URL;
	$vast_tok = defined( 'WTBP_AMCOEUR_TOKEN' ) && WTBP_AMCOEUR_TOKEN;
	$test     = '';
	if ( isset( $_GET['wtbp_test'] ) && check_admin_referer( 'wtbp_pp_test' ) ) { // phpcs:ignore
		$test = wtbp_pp_test_verbinding();
	}
	?>
	<div class="wrap">
		<h1>Productpagina (Apparatenman)</h1>
		<p>Levertijd uit AM Coeur, telefoon/WhatsApp en betaalmethoden op elke productpagina. Plugin-versie <?php echo esc_html( WTBP_VERSION ); ?>.</p>
		<?php if ( $test ) : ?>
			<div class="notice <?php echo 0 === strpos( $test, 'OK' ) ? 'notice-success' : 'notice-error'; ?>"><p><?php echo esc_html( $test ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'wtbp_pp' ); ?>
			<h2>Levertijd uit AM Coeur</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wtbp_url">AM Coeur-URL</label></th>
					<td>
						<input id="wtbp_url" name="<?php echo esc_attr( WTBP_PP_OPTION ); ?>[amcoeur_url]" type="url" class="regular-text" value="<?php echo esc_attr( $o['amcoeur_url'] ); ?>" placeholder="https://app.amcoeur.com" <?php disabled( $vast_url ); ?> />
						<?php if ( $vast_url ) : ?><p class="description">Vast ingesteld in wp-config.php.</p><?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wtbp_tok">Inkomend token</label></th>
					<td>
						<input id="wtbp_tok" name="<?php echo esc_attr( WTBP_PP_OPTION ); ?>[amcoeur_token]" type="password" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo $o['amcoeur_token'] ? '•••••••• (ingesteld, leeg laten = behouden)' : 'plak het token uit AM Coeur'; ?>" <?php disabled( $vast_tok ); ?> />
						<p class="description">AM Coeur → Instellingen → Webshops → Koppeling → "Inkomend token". Typ <code>__wis__</code> om het te verwijderen.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Levertijd tonen</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( WTBP_PP_OPTION ); ?>[levertijd_aan]" value="1" <?php checked( '1', $o['levertijd_aan'] ); ?> /> De voorraadregel ("Op voorraad") vervangen door de leverbelofte uit AM Coeur ("Morgen in huis", "Levertijd 3-5 werkdagen").</label></td>
				</tr>
				<tr>
					<th scope="row"><label for="wtbp_cache">Cache (minuten)</label></th>
					<td><input id="wtbp_cache" name="<?php echo esc_attr( WTBP_PP_OPTION ); ?>[cache_minuten]" type="number" min="5" max="1440" value="<?php echo esc_attr( $o['cache_minuten'] ); ?>" /> <span class="description">Hoe lang een levertijd per product bewaard blijft (standaard 240).</span></td>
				</tr>
			</table>

			<h2>Vertrouwensblok onder de koopknop</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Blok tonen</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( WTBP_PP_OPTION ); ?>[blok_aan]" value="1" <?php checked( '1', $o['blok_aan'] ); ?> /> Telefoon/WhatsApp, betaalmethoden en reviewlink tonen.</label></td>
				</tr>
				<tr>
					<th scope="row"><label for="wtbp_tel">Telefoonnummer</label></th>
					<td><input id="wtbp_tel" name="<?php echo esc_attr( WTBP_PP_OPTION ); ?>[telefoon]" type="text" class="regular-text" value="<?php echo esc_attr( $o['telefoon'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wtbp_wa">WhatsApp-nummer</label></th>
					<td><input id="wtbp_wa" name="<?php echo esc_attr( WTBP_PP_OPTION ); ?>[whatsapp]" type="text" class="regular-text" value="<?php echo esc_attr( $o['whatsapp'] ); ?>" placeholder="31722029162" /> <span class="description">Internationaal, zonder + of spaties. Leeg = geen WhatsApp-knop.</span></td>
				</tr>
				<tr>
					<th scope="row"><label for="wtbp_open">Bereikbaar</label></th>
					<td><input id="wtbp_open" name="<?php echo esc_attr( WTBP_PP_OPTION ); ?>[openingstijden]" type="text" class="regular-text" value="<?php echo esc_attr( $o['openingstijden'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wtbp_pay">Betaalmethoden</label></th>
					<td><input id="wtbp_pay" name="<?php echo esc_attr( WTBP_PP_OPTION ); ?>[betaalmethoden]" type="text" class="regular-text" value="<?php echo esc_attr( $o['betaalmethoden'] ); ?>" placeholder="<?php echo esc_attr( implode( ', ', wtbp_pp_betaalmethoden_auto() ) ); ?>" /> <span class="description">Komma-gescheiden. Leeg = automatisch de actieve betaalmethoden van WooCommerce (nu: <?php echo esc_html( implode( ', ', wtbp_pp_betaalmethoden_auto() ) ?: '—' ); ?>).</span></td>
				</tr>
				<tr>
					<th scope="row"><label for="wtbp_rev">Reviewlink</label></th>
					<td>
						<input id="wtbp_rev" name="<?php echo esc_attr( WTBP_PP_OPTION ); ?>[review_url]" type="url" class="regular-text" value="<?php echo esc_attr( $o['review_url'] ); ?>" placeholder="https://www.google.com/maps/place/…" /><br />
						<input name="<?php echo esc_attr( WTBP_PP_OPTION ); ?>[review_tekst]" type="text" class="regular-text" value="<?php echo esc_attr( $o['review_tekst'] ); ?>" style="margin-top:6px" />
						<p class="description">Bijvoorbeeld je Google-reviews of Kiyoh/Trustpilot-pagina. Leeg = geen regel.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Instellingen opslaan' ); ?>
		</form>
		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wtbp_test', '1' ), 'wtbp_pp_test' ) ); ?>">Verbinding met AM Coeur testen</a>
		</p>
	</div>
	<?php
}

function wtbp_pp_test_verbinding() {
	$o = wtbp_pp_opties();
	if ( ! $o['amcoeur_url'] || ! $o['amcoeur_token'] ) {
		return 'Vul eerst de AM Coeur-URL en het inkomend token in.';
	}
	$r = wp_remote_get( $o['amcoeur_url'] . '/api/wgt/ping', array(
		'timeout' => 8,
		'headers' => array( 'Authorization' => 'Bearer ' . $o['amcoeur_token'], 'Accept' => 'application/json' ),
	) );
	if ( is_wp_error( $r ) ) {
		return 'Mislukt: ' . $r->get_error_message();
	}
	$code = wp_remote_retrieve_response_code( $r );
	$body = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( 200 === $code && ! empty( $body['ok'] ) ) {
		return 'OK: verbonden met AM Coeur (webshop "' . ( $body['shop'] ?? '?' ) . '").';
	}
	if ( 401 === $code ) {
		return 'Mislukt: AM Coeur kent dit token niet (401). Maak een nieuw inkomend token aan bij Instellingen → Webshops → Koppeling.';
	}
	return 'Mislukt: AM Coeur gaf status ' . $code . '.';
}

/* ---- Betaalmethoden: automatisch uit WooCommerce ----------------------------- */
function wtbp_pp_betaalmethoden_auto() {
	$cached = get_transient( 'wtbp_pp_betaalmethoden' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$namen = array();
	if ( function_exists( 'WC' ) && WC()->payment_gateways ) {
		foreach ( WC()->payment_gateways->payment_gateways() as $gw ) {
			if ( 'yes' !== $gw->enabled ) {
				continue;
			}
			$titel = trim( wp_strip_all_tags( $gw->get_title() ) );
			// Interne/zakelijke methoden niet op de consumentenpagina.
			if ( '' === $titel || preg_match( '/rekening|factuur|cheque|bankoverschrijving|cod|contant/i', $titel . ' ' . $gw->id ) ) {
				continue;
			}
			$namen[ strtolower( $titel ) ] = $titel;
		}
	}
	$namen = array_values( $namen );
	set_transient( 'wtbp_pp_betaalmethoden', $namen, 6 * HOUR_IN_SECONDS );
	return $namen;
}

function wtbp_pp_betaalmethoden() {
	$o = wtbp_pp_opties();
	if ( '' !== trim( $o['betaalmethoden'] ) ) {
		return array_values( array_filter( array_map( 'trim', explode( ',', $o['betaalmethoden'] ) ) ) );
	}
	return wtbp_pp_betaalmethoden_auto();
}

/* ---- Levertijd uit AM Coeur ------------------------------------------------- */
/**
 * @return array|null  ['tekst' => …, 'klasse' => …, 'werkdagen_min' => …, 'werkdagen_max' => …] of null.
 */
function wtbp_pp_levertijd( $product ) {
	$o = wtbp_pp_opties();
	if ( '1' !== $o['levertijd_aan'] || ! $o['amcoeur_url'] || ! $o['amcoeur_token'] || ! $product ) {
		return null;
	}
	$id  = (int) $product->get_id();
	$key = 'wtbp_lt_' . $id;
	$hit = get_transient( $key );
	if ( is_array( $hit ) ) {
		return empty( $hit['gevonden'] ) ? null : $hit;
	}
	$ean = '';
	if ( method_exists( $product, 'get_global_unique_id' ) ) {
		$ean = (string) $product->get_global_unique_id();
	}
	if ( '' === $ean ) {
		$ean = (string) $product->get_meta( '_global_unique_id', true );
	}
	$url = add_query_arg( array(
		'woo_id' => $id,
		'sku'    => (string) $product->get_sku(),
		'ean'    => preg_replace( '/\D/', '', $ean ),
	), $o['amcoeur_url'] . '/api/wgt/levertijd' );
	$r = wp_remote_get( $url, array(
		'timeout' => 3,
		'headers' => array( 'Authorization' => 'Bearer ' . $o['amcoeur_token'], 'Accept' => 'application/json' ),
	) );
	$ttl = max( 5, (int) $o['cache_minuten'] ) * MINUTE_IN_SECONDS;
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		// Storing: 15 minuten niets tonen, daarna opnieuw proberen. De pagina blijft gewoon werken.
		set_transient( $key, array( 'gevonden' => false ), 15 * MINUTE_IN_SECONDS );
		return null;
	}
	$d = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( ! is_array( $d ) || empty( $d['gevonden'] ) || empty( $d['tekst'] ) ) {
		set_transient( $key, array( 'gevonden' => false ), $ttl );
		return null;
	}
	$uit = array(
		'gevonden'      => true,
		'tekst'         => sanitize_text_field( $d['tekst'] ),
		'klasse'        => sanitize_key( $d['klasse'] ?? '' ),
		'werkdagen_min' => isset( $d['werkdagen_min'] ) ? (int) $d['werkdagen_min'] : null,
		'werkdagen_max' => isset( $d['werkdagen_max'] ) ? (int) $d['werkdagen_max'] : null,
	);
	set_transient( $key, $uit, $ttl );
	return $uit;
}

/* ---- HTML van het vertrouwensblok ------------------------------------------- */
function wtbp_pp_trust_html() {
	$o = wtbp_pp_opties();
	if ( '1' !== $o['blok_aan'] ) {
		return '';
	}
	$regels = array();
	$tel    = trim( $o['telefoon'] );
	$wa     = trim( $o['whatsapp'] );
	if ( $tel || $wa ) {
		$links = array();
		if ( $tel ) {
			$links[] = '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $tel ) ) . '">' . esc_html( $tel ) . '</a>';
		}
		if ( $wa ) {
			$links[] = '<a href="https://wa.me/' . esc_attr( $wa ) . '" target="_blank" rel="noopener">WhatsApp</a>';
		}
		$open     = $o['openingstijden'] ? ' <span class="wtbp-muted">(' . esc_html( $o['openingstijden'] ) . ')</span>' : '';
		$regels[] = array( 'tel', 'Vragen over dit apparaat? Bel of app ons: ' . implode( ' · ', $links ) . $open );
	}
	$pay = wtbp_pp_betaalmethoden();
	if ( $pay ) {
		$regels[] = array( 'pay', 'Veilig betalen met ' . esc_html( implode( ', ', $pay ) ) );
	}
	if ( $o['review_url'] ) {
		$regels[] = array( 'star', '<a href="' . esc_url( $o['review_url'] ) . '" target="_blank" rel="noopener">' . esc_html( $o['review_tekst'] ?: 'Lees de ervaringen van onze klanten' ) . '</a>' );
	}
	if ( ! $regels ) {
		return '';
	}
	$icons = array(
		'tel'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6.2 6.2l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg>',
		'pay'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>',
		'star' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.1 8.3 22 9.3 17 14.1 18.2 21 12 17.8 5.8 21 7 14.1 2 9.3 8.9 8.3 12 2"/></svg>',
	);
	$html = '<div class="wtbp-trust">';
	foreach ( $regels as $r ) {
		$html .= '<div class="wtbp-trust-row wtbp-trust-' . $r[0] . '">' . $icons[ $r[0] ] . '<span>' . $r[1] . '</span></div>';
	}
	$html .= '</div>';
	return $html;
}

/* ---- Vangnet via WooCommerce-hooks (als het thema die wél aanroept) ---------- */
add_action( 'woocommerce_after_add_to_cart_form', function () {
	if ( is_product() ) {
		echo wtbp_pp_trust_html(); // phpcs:ignore WordPress.Security.EscapeOutput — opgebouwd met esc_*.
	}
}, 20 );

/* ---- Script + stijl op de productpagina ------------------------------------- */
add_action( 'wp_enqueue_scripts', 'wtbp_pp_assets', 25 );
function wtbp_pp_assets() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	$product = wc_get_product( get_queried_object_id() );
	if ( ! $product ) {
		return;
	}
	$lt    = wtbp_pp_levertijd( $product );
	$trust = wtbp_pp_trust_html();
	if ( ! $lt && ! $trust ) {
		return;
	}
	$b   = wtbp_brand();
	$css = '
	.wtbp-trust{margin:18px 0 6px;padding:14px 16px;border:1px solid ' . $b['border'] . ';border-radius:' . $b['radius'] . ';background:' . $b['surface'] . ';display:flex;flex-direction:column;gap:9px;font-family:' . $b['font'] . ';font-size:14px;line-height:22px;color:' . $b['text'] . '}
	.wtbp-trust-row{display:flex;align-items:flex-start;gap:10px}
	.wtbp-trust-row svg{flex:0 0 18px;margin-top:2px;color:' . $b['primary'] . '}
	.wtbp-trust a{color:' . $b['primary'] . ';font-weight:600;text-decoration:underline}
	.wtbp-trust .wtbp-muted{color:' . $b['muted'] . '}
	.wtbp-levertijd{display:flex;align-items:center;gap:8px;font-size:16px;line-height:24px;font-weight:600;color:' . $b['accent_dark'] . '}
	.wtbp-levertijd.is-lang{color:' . $b['text'] . '}
	.wtbp-levertijd svg{flex:0 0 20px}
	';
	wp_register_style( 'wtbp-pp', false, array(), WTBP_VERSION );
	wp_enqueue_style( 'wtbp-pp' );
	wp_add_inline_style( 'wtbp-pp', $css );

	wp_register_script( 'wtbp-pp', '', array(), WTBP_VERSION, true );
	wp_enqueue_script( 'wtbp-pp' );
	wp_add_inline_script( 'wtbp-pp', 'window.wtbpPP=' . wp_json_encode( array(
		'levertijd' => $lt ? array( 'tekst' => $lt['tekst'], 'klasse' => $lt['klasse'] ) : null,
		'trust'     => $trust,
	) ) . ';', 'before' );
	wp_add_inline_script( 'wtbp-pp', wtbp_pp_js() );
}

function wtbp_pp_js() {
	return <<<'JS'
(function(){
  var d = window.wtbpPP || {};
  function run(){
    var summary = document.querySelector('.summary.entry-summary, .summary, .product .entry-summary');
    if (!summary) return;
    var form = summary.querySelector('form.cart') || summary;
    // 1. Levertijd: de WooCommerce-voorraadregel vervangen door de leverbelofte.
    if (d.levertijd && d.levertijd.tekst && !summary.querySelector('.wtbp-levertijd')) {
      var stock = summary.querySelector('p.stock, .stock');
      var lang = d.levertijd.klasse !== 'voorraad';
      var icon = lang
        ? '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>'
        : '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
      var el = document.createElement('div');
      el.className = 'wtbp-levertijd' + (lang ? ' is-lang' : '');
      el.innerHTML = icon + '<span></span>';
      el.querySelector('span').textContent = d.levertijd.tekst;
      if (stock) {
        // De regel (icoon + "Op voorraad") van het thema vervangen, zodat er niets tegenstrijdigs staat.
        var row = stock.closest('div') || stock;
        row.parentNode.replaceChild(el, row);
      } else {
        form.insertBefore(el, form.firstChild);
      }
    }
    // 2. Vertrouwensblok onder de koopknop (alleen als de hook het niet al deed).
    if (d.trust && !document.querySelector('.wtbp-trust')) {
      var wrap = document.createElement('div');
      wrap.innerHTML = d.trust;
      if (form.parentNode === summary) {
        form.insertAdjacentElement('afterend', wrap.firstChild);
      } else {
        summary.appendChild(wrap.firstChild);
      }
    }
  }
  // 3. Toont het thema hetzelfde telefoonnummer al op deze pagina (blok "Interesse? Vraag prijs aan"),
  //    dan laten wij onze telefoonregel weg, zodat het nummer er niet twee keer staat.
  function ontdubbel(){
    var rij = document.querySelector('.wtbp-trust .wtbp-trust-tel');
    if (!rij) return;
    var eigen = rij.querySelector('a[href^="tel:"]');
    if (!eigen) return;
    var cijfers = function(a){ return (a.getAttribute('href') || '').replace(/\D/g, '').slice(-9); };
    var nummer = cijfers(eigen);
    var gebied = document.querySelector('main') || document.body;
    var dubbel = Array.prototype.some.call(gebied.querySelectorAll('a[href^="tel:"]'), function(a){
      return !a.closest('.wtbp-trust') && nummer && cijfers(a) === nummer;
    });
    if (dubbel) {
      var blok = rij.parentNode;
      blok.removeChild(rij);
      if (!blok.querySelector('.wtbp-trust-row')) blok.parentNode.removeChild(blok);
    }
  }
  function alles(){ run(); ontdubbel(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', alles); else alles();
})();
JS;
}

/* =========================================================================
 * 8. AUTOMATISCHE UPDATES (zelf-hostend)
 * =========================================================================
 * Laat WordPress periodiek WTBP_UPDATE_URL controleren. Staat daar een
 * hogere versie, dan verschijnt bij Plugins een normale "Update beschikbaar"-
 * knop – geen handmatige upload meer nodig. Werkt met een JSON-bestand op je
 * eigen server of met een raw JSON-URL van GitHub.
 * ========================================================================= */
if ( ! class_exists( 'WTBP_Updater' ) ) {

	class WTBP_Updater {

		private $file;
		private $basename;
		private $slug;

		public function __construct( $file ) {
			$this->file     = $file;
			$this->basename = plugin_basename( $file );          // map/bestand.php
			$this->slug     = dirname( $this->basename );        // mapnaam
			if ( '.' === $this->slug ) {
				$this->slug = basename( $file, '.php' );
			}

			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
			add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
			add_filter( 'upgrader_source_selection', array( $this, 'fix_folder_name' ), 10, 4 );
			add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ), 10, 2 );
			// "Opnieuw controleren" (Dashboard → Updates) verse check laten doen.
			add_action( 'load-update-core.php', array( $this, 'force_fresh_check' ) );
		}

		/** Cache legen zodat "Opnieuw controleren" direct GitHub raadpleegt. */
		public function force_fresh_check() {
			$url = $this->update_url();
			if ( '' !== $url ) {
				delete_transient( 'wtbp_update_' . md5( $url ) );
			}
		}

		/** URL naar het update-JSON (leeg = uit). */
		private function update_url() {
			$url = defined( 'WTBP_UPDATE_URL' ) ? WTBP_UPDATE_URL : '';
			return trim( (string) apply_filters( 'wtbp_update_url', $url ) );
		}

		/** Haal de metadata op (met korte cache om de server te ontzien). */
		private function get_remote() {
			$url = $this->update_url();
			if ( '' === $url ) {
				return false;
			}

			$cache_key = 'wtbp_update_' . md5( $url );
			$cached    = get_transient( $cache_key );
			if ( false !== $cached ) {
				return 'none' === $cached ? false : $cached;
			}

			$response = wp_remote_get( $url, array(
				'timeout' => 15,
				'headers' => array( 'Accept' => 'application/json' ),
			) );

			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				set_transient( $cache_key, 'none', 5 * MINUTE_IN_SECONDS );
				return false;
			}

			$data = json_decode( wp_remote_retrieve_body( $response ) );
			if ( empty( $data ) || empty( $data->version ) || empty( $data->download_url ) ) {
				set_transient( $cache_key, 'none', 5 * MINUTE_IN_SECONDS );
				return false;
			}

			set_transient( $cache_key, $data, 6 * HOUR_IN_SECONDS );
			return $data;
		}

		/** Voeg de update toe aan de lijst als er een hogere versie is. */
		public function check_for_update( $transient ) {
			if ( empty( $transient->checked ) ) {
				return $transient;
			}

			$remote  = $this->get_remote();
			$current = isset( $transient->checked[ $this->basename ] ) ? $transient->checked[ $this->basename ] : '';

			if ( $remote && $current && version_compare( $remote->version, $current, '>' ) ) {
				$obj               = new stdClass();
				$obj->slug         = $this->slug;
				$obj->plugin       = $this->basename;
				$obj->new_version  = $remote->version;
				$obj->package      = $remote->download_url;
				$obj->url          = isset( $remote->homepage ) ? $remote->homepage : '';
				$obj->tested       = isset( $remote->tested ) ? $remote->tested : '';
				$obj->requires     = isset( $remote->requires ) ? $remote->requires : '';
				$obj->requires_php = isset( $remote->requires_php ) ? $remote->requires_php : '';
				$transient->response[ $this->basename ] = $obj;
			}

			return $transient;
		}

		/** Vult het "Details bekijken"-scherm van de plugin. */
		public function plugin_info( $result, $action, $args ) {
			if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->slug ) {
				return $result;
			}

			$remote = $this->get_remote();
			if ( ! $remote ) {
				return $result;
			}

			$info               = new stdClass();
			$info->name         = isset( $remote->name ) ? $remote->name : 'Apparatenman Betaal- & Bedankpagina';
			$info->slug         = $this->slug;
			$info->version      = $remote->version;
			$info->author       = isset( $remote->author ) ? $remote->author : 'Apparatenman';
			$info->requires     = isset( $remote->requires ) ? $remote->requires : '';
			$info->tested       = isset( $remote->tested ) ? $remote->tested : '';
			$info->requires_php = isset( $remote->requires_php ) ? $remote->requires_php : '';
			$info->last_updated = isset( $remote->last_updated ) ? $remote->last_updated : '';
			$info->download_link = $remote->download_url;
			$info->sections     = isset( $remote->sections ) ? (array) $remote->sections : array( 'changelog' => '' );

			return $info;
		}

		/**
		 * Zorg dat de uitgepakte map de juiste naam heeft (belangrijk bij o.a.
		 * GitHub-zips die een andere mapnaam gebruiken).
		 */
		public function fix_folder_name( $source, $remote_source, $upgrader, $args = array() ) {
			if ( empty( $args['plugin'] ) || $args['plugin'] !== $this->basename ) {
				return $source;
			}
			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				return $source;
			}
			$desired = trailingslashit( $remote_source ) . $this->slug . '/';
			if ( untrailingslashit( $source ) === untrailingslashit( $desired ) ) {
				return $source;
			}
			if ( $wp_filesystem->move( $source, $desired ) ) {
				return $desired;
			}
			return $source;
		}

		/** Cache leegmaken na een update, zodat de melding verdwijnt. */
		public function clear_cache( $upgrader, $options ) {
			$action = isset( $options['action'] ) ? $options['action'] : '';
			$type   = isset( $options['type'] ) ? $options['type'] : '';
			if ( 'update' === $action && 'plugin' === $type ) {
				$url = $this->update_url();
				if ( '' !== $url ) {
					delete_transient( 'wtbp_update_' . md5( $url ) );
				}
			}
		}
	}

	add_action( 'admin_init', function () {
		new WTBP_Updater( __FILE__ );
	} );
}
