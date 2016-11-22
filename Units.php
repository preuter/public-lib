<?php
 /**
  * Perform unit conversion.
  *
  * @file Units.php
  * @date 2011-06-06 11:49 HST
  * @author Paul Reuter
  * @version 2.2.2
  *
  * @modifications <pre>
  * 1.0.0 - ? - Initial naive implementation.
  * 2.0.0 - 2010-03-23 - Parsed from grammar spec.
  * 2.1.0 - 2010-03-24 - Dropped grammar support (flawed logic).
  * 2.1.1 - 2010-03-26 - Finally finished.
  * 2.2.0 - 2010-04-12 - out = in * scale + offset; common conversion format.
  * 2.2.1 - 2010-07-14 - BugFix: id_term negative number look-left
  * 2.2.2 - 2011-06-06 - Add: Comments!
  * </pre>
  */


/**
 * <pre>
 * Unit conversion is everywhere.  Don't crash that satellite!  
 * Double check your calculations before launch.
 *
 * NB: <si_value> = <input> * toSI_scale + toSI_offset;
 *
 * Usage Senarios:
 *  *  $deg_C = Units::Convert("32 deg_F","deg_C");
 *  *  $deg_C = Units::Convert("deg_F","deg_C",32);
 *  *  $deg_C = Units::Convert("32 deg_F to deg_C");
 *
 *  *  Convert from "12 inches" to SI to "cm":
 *     $u = new Units();
 *     $value_m  = $u->toSI("12 inches");
 *     $value_cm = $u->fromSI("cm",$value_m);
 *  *  Convert from deg_F to deg_C for bulk callback:
 *     $u = new Units();
 *     list($s_f,$o_f) = $u->getToSI("deg_F");
 *     list($s_t,$o_t) = $u->getToSI("deg_C");
 *     $value = ((212 * $s_f + $o_f) - $o_t) / $s_t;
 *
 * Example Walk-through: `Convert deg_F to deg_C`
 *     $u = new Units();
 *     list($s_f,$o_f) = $u->getToSI("deg_F"); // returns (0.555556,255.37222)
 *     list($s_t,$o_t) = $u->getToSI("deg_C"); // returns (1,273.15)
 *     $value = ((212 * $s_f + $o_f) - $o_t) / $s_t;
 *      ?     = ((212 * 0.5555556 + 255.37222) - 273.15 ) / 1
 *      ?     = ( 117.7777777778  + 255.37222) - 273.15 ) / 1
 *      ?     = (          373.149998          - 273.15
 *     $value = 99.99999998
 * </pre>
 *
 * @package Core
 * @subpackage Utilities
 */
class Units {
  /**
   * @var hash Stores SI prefixes (tera,peta,etc...) and their scale factors.
   * @access private
   */
  var $prefixes = array(
    "yotta" =>   array(
      "name" => "yotta",
      "value" => 1.0E+24,
      "symbol" => "Y"
    ),
    "zetta" =>   array(
      "name" => "zetta",
      "value" => 1.0E+21,
      "symbol" => "Z"
    ),
    "exa" =>   array(
      "name" => "exa",
      "value" => 1.0E+18,
      "symbol" => "E"
    ),
    "peta" =>   array(
      "name" => "peta",
      "value" => 1.0E+15,
      "symbol" => "P"
    ),
    "tera" =>   array(
      "name" => "tera",
      "value" => 1000000000000,
      "symbol" => "T"
    ),
    "giga" =>   array(
      "name" => "giga",
      "value" => 1000000000,
      "symbol" => "G"
    ),
    "mega" =>   array(
      "name" => "mega",
      "value" => 1000000,
      "symbol" => "M"
    ),
    "kilo" =>   array(
      "name" => "kilo",
      "value" => 1000,
      "symbol" => "k"
    ),
    "hecto" =>   array(
      "name" => "hecto",
      "value" => 100,
      "symbol" => "h"
    ),
    "deka" =>   array(
      "name" => "deka",
      "value" => 10,
      "symbol" => "da"
    ),
    "deci" =>   array(
      "name" => "deci",
      "value" => 0.1,
      "symbol" => "d"
    ),
    "centi" =>   array(
      "name" => "centi",
      "value" => 0.01,
      "symbol" => "c"
    ),
    "milli" =>   array(
      "name" => "milli",
      "value" => 0.001,
      "symbol" => "m"
    ),
    "micro" =>   array(
      "name" => "micro",
      "value" => 1.0E-6,
      "symbol" => "&#xB5;"
    ),
    "nano" =>   array(
      "name" => "nano",
      "value" => 1.0E-9,
      "symbol" => "n"
    ),
    "pico" =>   array(
      "name" => "pico",
      "value" => 1.0E-12,
      "symbol" => "p"
    ),
    "femto" =>   array(
      "name" => "femto",
      "value" => 1.0E-15,
      "symbol" => "f"
    ),
    "atto" =>   array(
      "name" => "atto",
      "value" => 1.0E-18,
      "symbol" => "a"
    ),
    "zepto" =>   array(
      "name" => "zepto",
      "value" => 1.0E-21,
      "symbol" => "z"
    ),
    "yocto" =>   array(
      "name" => "yocto",
      "value" => 1.0E-24,
      "symbol" => "y"
    )
  );

  /**
   * @var hash Stores unit names, definitions, dimensions and symbols.
   * @access private
   */
  var $units = array(
    array(
      "name" => array(
        array(
          "meter",
          "meters"
        ),
        array(
          "metre",
          "metres"
        )
      ),
      "symbol" => array(
        "m"
      ),
      "def" => null,
      "isBase" => true,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "kilogram",
          "kilograms"
        )
      ),
      "symbol" => array(
        "kg"
      ),
      "def" => null,
      "isBase" => true,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "second",
          "seconds"
        )
      ),
      "symbol" => array(
        "s"
      ),
      "def" => null,
      "isBase" => true,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "ampere",
          "amperes"
        )
      ),
      "symbol" => array(
        "A"
      ),
      "def" => null,
      "isBase" => true,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "kelvin",
          "kelvins"
        )
      ),
      "symbol" => array(
        "K"
      ),
      "def" => null,
      "isBase" => true,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "mole",
          "moles"
        )
      ),
      "symbol" => array(
        "mol"
      ),
      "def" => null,
      "isBase" => true,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "candela",
          "candelas"
        )
      ),
      "symbol" => array(
        "cd"
      ),
      "def" => null,
      "isBase" => true,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "radian",
          "radians"
        )
      ),
      "symbol" => array(
        "rad"
      ),
      "def" => null,
      "isBase" => false,
      "hasDimension" => false
    ),
    array(
      "name" => array(
        array(
          "steradian",
          "steradians"
        )
      ),
      "symbol" => array(
        "sr"
      ),
      "def" => "rad^2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "hertz",
          "hertzs"
        )
      ),
      "symbol" => array(
        "Hz"
      ),
      "def" => "1/s",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "gram",
          "grams"
        )
      ),
      "symbol" => array(
        "g"
      ),
      "def" => "1e-3 kg",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "newton",
          "newtons"
        )
      ),
      "symbol" => array(
        "N"
      ),
      "def" => "m.kg/s^2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "pascal",
          "pascals"
        )
      ),
      "symbol" => array(
        "Pa"
      ),
      "def" => "N/m^2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "joule",
          "joules"
        )
      ),
      "symbol" => array(
        "J"
      ),
      "def" => "N.m",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "watt",
          "watts"
        )
      ),
      "symbol" => array(
        "W"
      ),
      "def" => "J/s",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "coulomb",
          "coulombs"
        )
      ),
      "symbol" => array(
        "C"
      ),
      "def" => "s.A",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "volt",
          "volts"
        )
      ),
      "symbol" => array(
        "V"
      ),
      "def" => "W/A",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "farad",
          "farads"
        )
      ),
      "symbol" => array(
        "F"
      ),
      "def" => "C/V",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "ohm",
          "ohms"
        )
      ),
      "symbol" => array(
        "&#x3A9;",
        "&#x2126;"
      ),
      "def" => "V/A",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "siemens",
          "siemenss"
        )
      ),
      "symbol" => array(
        "S"
      ),
      "def" => "A/V",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "weber",
          "webers"
        )
      ),
      "symbol" => array(
        "Wb"
      ),
      "def" => "V.s",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "tesla",
          "teslas"
        )
      ),
      "symbol" => array(
        "T"
      ),
      "def" => "Wb/m^2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "henry",
          "henrys"
        )
      ),
      "symbol" => array(
        "H"
      ),
      "def" => "Wb/A",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "degree_Celsius",
          "degrees_Celsius"
        )
      ),
      "symbol" => array(
        "&#xB0;C"
      ),
      "def" => "K @ 273.15",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "lumen",
          "lumens"
        )
      ),
      "symbol" => array(
        "lm"
      ),
      "def" => "cd.sr",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "lux",
          "luxs"
        )
      ),
      "symbol" => array(
        "lx"
      ),
      "def" => "lm/m^2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "katal",
          "katals"
        )
      ),
      "symbol" => array(
        "kat"
      ),
      "def" => "mol/s",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "becquerel",
          "becquerels"
        )
      ),
      "symbol" => array(
        "Bq"
      ),
      "def" => "1/s",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "gray",
          "grays"
        )
      ),
      "symbol" => array(
        "Gy"
      ),
      "def" => "J/kg",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "sievert",
          "sieverts"
        )
      ),
      "symbol" => array(
        "Sv"
      ),
      "def" => "J/kg",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "minute",
          "minutes"
        )
      ),
      "symbol" => array(
        "min"
      ),
      "def" => "60 s",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "hour",
          "hours"
        )
      ),
      "symbol" => array(
        "h",
        "hr"
      ),
      "def" => "60 min",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "day",
          "days"
        )
      ),
      "symbol" => array(
        "d"
      ),
      "def" => "24 h",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "pi",
          null
        )
      ),
      "symbol" => array(
        "&#x3C0;"
      ),
      "def" => "3.141592653589793238462643383279",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "arc_degree",
          "arc_degrees"
        ),
        array(
          "angular_degree",
          "angular_degrees"
        ),
        array(
          "degree",
          "degrees"
        ),
        array(
          "arcdeg",
          "arcdegs"
        )
      ),
      "symbol" => array(
        "&#xB0;"
      ),
      "def" => "(pi/180) rad",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "arc_minute",
          "arc_minutes"
        ),
        array(
          "angular_minute",
          "angular_minutes"
        ),
        array(
          "arcminute",
          "arcminutes"
        ),
        array(
          "arcmin",
          "arcmins"
        )
      ),
      "symbol" => array(
        "'",
        "&#x2032;"
      ),
      "def" => "arc_degree/60",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "arc_second",
          "arc_seconds"
        ),
        array(
          "angular_second",
          "angular_seconds"
        ),
        array(
          "arcsecond",
          "arcseconds"
        ),
        array(
          "arcsec",
          "arcsecs"
        )
      ),
      "symbol" => array(
        "&quot;",
        "&#x2033;"
      ),
      "def" => "arc_minute/60",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "liter",
          "liters"
        ),
        array(
          "litre",
          "litres"
        )
      ),
      "symbol" => array(
        "L",
        "l"
      ),
      "def" => "dm^3",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "metric_ton",
          "metric_tons"
        ),
        array(
          "tonne",
          "tonnes"
        )
      ),
      "symbol" => array(
        "t"
      ),
      "def" => "1000 kg",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "electronvolt",
          "electronvolts"
        ),
        array(
          "electron_volt",
          "electron_volts"
        )
      ),
      "symbol" => array(
        "eV"
      ),
      "def" => "1.60217733e-19 J",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "unified_atomic_mass_unit",
          "unified_atomic_mass_units"
        ),
        array(
          "atomic_mass_unit",
          null
        ),
        array(
          "atomicmassunit",
          null
        ),
        array(
          "amu",
          null
        )
      ),
      "symbol" => array(
        "u"
      ),
      "def" => "1.6605402e-27 kg",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "astronomical_unit",
          "astronomical_units"
        )
      ),
      "symbol" => array(
        "ua"
      ),
      "def" => "1.495979e11 m",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "nautical_mile",
          "nautical_miles"
        )
      ),
      "symbol" => array(),
      "def" => "1852 m",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "international_knot",
          "international_knots"
        ),
        array(
          "knot_international",
          "knot_internationals"
        ),
        array(
          "knot",
          "knots"
        )
      ),
      "symbol" => array(),
      "def" => "nautical_mile/hour",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "angstrom",
          "angstroms"
        ),
        array(
          "&#xE5;ngstr&#xF6;m",
          "&#xE5;ngstr&#xF6;ms"
        )
      ),
      "symbol" => array(
        "&#xC5;",
        "&#x212B;"
      ),
      "def" => "1e-10 m",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "are",
          "ares"
        )
      ),
      "symbol" => array(
        "a"
      ),
      "def" => "dam^2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "hectare",
          "hectares"
        )
      ),
      "symbol" => array(),
      "def" => "100 are",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "barn",
          "barns"
        )
      ),
      "symbol" => array(
        "b"
      ),
      "def" => "100 fm^2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "bar",
          "bars"
        )
      ),
      "symbol" => array(),
      "def" => "1000 hPa",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "gal",
          "gals"
        )
      ),
      "symbol" => array(),
      "def" => "cm/s^2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "curie",
          "curies"
        )
      ),
      "symbol" => array(
        "Ci"
      ),
      "def" => "3.7e10 Bq",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "roentgen",
          "roentgens"
        )
      ),
      "symbol" => array(
        "R"
      ),
      "def" => "2.58e-4 C/kg",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "rem",
          "rems"
        )
      ),
      "symbol" => array(),
      "def" => "cSv",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "sec",
          "secs"
        )
      ),
      "symbol" => array(),
      "def" => "s",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "amp",
          "amps"
        )
      ),
      "symbol" => array(),
      "def" => "A",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "degree_kelvin",
          "degrees_kelvin"
        ),
        array(
          "degree_K",
          "degrees_K"
        ),
        array(
          "degreeK",
          "degreesK"
        ),
        array(
          "deg_K",
          "degs_K"
        ),
        array(
          "degK",
          "degsK"
        )
      ),
      "symbol" => array(
        "&#xB0;K"
      ),
      "def" => "K",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "candle",
          "candles"
        )
      ),
      "symbol" => array(),
      "def" => "cd",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "baud",
          "bauds"
        )
      ),
      "symbol" => array(
        "Bd",
        "bps"
      ),
      "def" => "Hz",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "celsius",
          "celsius"
        ),
        array(
          "degree_C",
          "degrees_C"
        ),
        array(
          "degreeC",
          "degreesC"
        ),
        array(
          "deg_C",
          "degs_C"
        ),
        array(
          "degC",
          "degsC"
        )
      ),
      "symbol" => array(
        "&#xB0;C",
        "&#x2103;",
        "\xb0C"
      ),
      "def" => "degree_Celsius",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(),
      "symbol" => array(
        "kt",
        "kts"
      ),
      "def" => "knot",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "avogadro_constant",
          null
        )
      ),
      "symbol" => array(),
      "def" => "6.02214179e23/mol",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "percent",
          null
        )
      ),
      "symbol" => array(
        "%"
      ),
      "def" => "0.01",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "grade",
          "grades"
        )
      ),
      "symbol" => array(),
      "def" => "0.9 arc_degree",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "circle",
          "circles"
        ),
        array(
          "cycle",
          "cycles"
        ),
        array(
          "turn",
          "turns"
        ),
        array(
          "revolution",
          "revolutions"
        ),
        array(
          "rotation",
          "rotations"
        )
      ),
      "symbol" => array(),
      "def" => "2 pi rad",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "degree_north",
          "degrees_north"
        ),
        array(
          "degree_N",
          "degrees_N"
        ),
        array(
          "degreeN",
          "degreesN"
        ),
        array(
          "degree_east",
          "degrees_east"
        ),
        array(
          "degree_E",
          "degrees_E"
        ),
        array(
          "degreeE",
          "degreesE"
        ),
        array(
          "degree_true",
          "degrees_true"
        ),
        array(
          "degree_T",
          "degrees_T"
        ),
        array(
          "degreeT",
          "degreesT"
        )
      ),
      "symbol" => array(),
      "def" => "arc_degree",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "degree_west",
          "degrees_west"
        ),
        array(
          "degree_W",
          "degrees_W"
        ),
        array(
          "degreeW",
          "degreesW"
        )
      ),
      "symbol" => array(),
      "def" => "-1 degree_east",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "assay_ton",
          "assay_tons"
        )
      ),
      "symbol" => array(),
      "def" => "2.916667e-2 kg",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "avoirdupois_ounce",
          "avoirdupois_ounces"
        )
      ),
      "symbol" => array(),
      "def" => "2.834952e-2 kg",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "avoirdupois_pound",
          "avoirdupois_pounds"
        ),
        array(
          "pound",
          "pounds"
        )
      ),
      "symbol" => array(
        "lb"
      ),
      "def" => "4.5359237e-1 kg",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "carat",
          "carats"
        )
      ),
      "symbol" => array(),
      "def" => "2e-4 kg",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "grain",
          "grains"
        )
      ),
      "symbol" => array(
        "gr"
      ),
      "def" => "6.479891e-5 kg",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "long_hundredweight",
          "long_hundredweights"
        )
      ),
      "symbol" => array(),
      "def" => "5.080235e1 kg",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "pennyweight",
          "pennyweights"
        )
      ),
      "symbol" => array(),
      "def" => "1.555174e-3 kg",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "short_hundredweight",
          "short_hundredweights"
        )
      ),
      "symbol" => array(),
      "def" => "4.535924e1 kg",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "slug",
          "slugs"
        )
      ),
      "symbol" => array(),
      "def" => "14.59390 kg",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "troy_ounce",
          "troy_ounces"
        ),
        array(
          "apothecary_ounce",
          "apothecary_ounces"
        )
      ),
      "symbol" => array(),
      "def" => "3.110348e-2 kg",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "troy_pound",
          "troy_pounds"
        ),
        array(
          "apothecary_pound",
          "apothecary_pounds"
        )
      ),
      "symbol" => array(),
      "def" => "3.732417e-1 kg",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "scruple",
          "scruples"
        )
      ),
      "symbol" => array(),
      "def" => "20 grain",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "apdram",
          "apdrams"
        )
      ),
      "symbol" => array(),
      "def" => "60 grain",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "apounce",
          "apounces"
        )
      ),
      "symbol" => array(),
      "def" => "480 grain",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "appound",
          "appounds"
        )
      ),
      "symbol" => array(),
      "def" => "5760 grain",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "bag",
          "bags"
        )
      ),
      "symbol" => array(),
      "def" => "94 pound",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "short_ton",
          "short_tons"
        ),
        array(
          "ton",
          "tons"
        )
      ),
      "symbol" => array(),
      "def" => "2000 pound",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "long_ton",
          "long_tons"
        )
      ),
      "symbol" => array(),
      "def" => "2240 pound",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "fermi",
          "fermis"
        )
      ),
      "symbol" => array(),
      "def" => "1e-15 m",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "light_year",
          "light_years"
        )
      ),
      "symbol" => array(),
      "def" => "9.46073e15 m",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "micron",
          "microns"
        )
      ),
      "symbol" => array(),
      "def" => "1e-6 m",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "mil",
          "mils"
        )
      ),
      "symbol" => array(),
      "def" => "2.54e-5 m",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "parsec",
          "parsecs"
        )
      ),
      "symbol" => array(),
      "def" => "3.085678e16 m",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "printers_point",
          "printers_points"
        )
      ),
      "symbol" => array(),
      "def" => "3.514598e-4 m",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "chain",
          "chains"
        )
      ),
      "symbol" => array(),
      "def" => "2.011684e1 m",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "printers_pica",
          "printers_picas"
        ),
        array(
          "pica",
          "picas"
        )
      ),
      "symbol" => array(),
      "def" => "12 printers_point",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "nmile",
          "nmiles"
        )
      ),
      "symbol" => array(),
      "def" => "nautical_mile",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "US_survey_foot",
          "US_survey_feet"
        )
      ),
      "symbol" => array(),
      "def" => "(1200/3937) m",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "US_survey_yard",
          "US_survey_yards"
        )
      ),
      "symbol" => array(),
      "def" => "3 US_survey_feet",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "US_survey_mile",
          "US_survey_miles"
        ),
        array(
          "US_statute_mile",
          "US_statute_miles"
        )
      ),
      "symbol" => array(),
      "def" => "5280 US_survey_feet",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "rod",
          "rods"
        ),
        array(
          "pole",
          "poles"
        ),
        array(
          "perch",
          "perchs"
        )
      ),
      "symbol" => array(),
      "def" => "16.5 US_survey_feet",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "furlong",
          "furlongs"
        )
      ),
      "symbol" => array(),
      "def" => "660 US_survey_feet",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "fathom",
          "fathoms"
        )
      ),
      "symbol" => array(),
      "def" => "6 US_survey_feet",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "international_inch",
          "international_inches"
        ),
        array(
          "inch",
          "inches"
        )
      ),
      "symbol" => array(
        "in"
      ),
      "def" => "2.54 cm",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "international_foot",
          "international_feet"
        ),
        array(
          "foot",
          "feet"
        )
      ),
      "symbol" => array(
        "ft"
      ),
      "def" => "12 international_inches",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "international_yard",
          "international_yards"
        ),
        array(
          "yard",
          "yards"
        )
      ),
      "symbol" => array(
        "yd"
      ),
      "def" => "3 international_feet",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "international_mile",
          "international_miles"
        ),
        array(
          "mile",
          "miles"
        )
      ),
      "symbol" => array(
        "mi"
      ),
      "def" => "5280 international_feet",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "big_point",
          "big_points"
        )
      ),
      "symbol" => array(),
      "def" => "inch/72",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "barleycorn",
          "barleycorns"
        )
      ),
      "symbol" => array(),
      "def" => "inch/3",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "arpentlin",
          "arpentlins"
        )
      ),
      "symbol" => array(),
      "def" => "191.835 foot",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "dobson",
          "dobsons"
        )
      ),
      "symbol" => array(
        "DU"
      ),
      "def" => "(2.69e20/avogadro_constant)/m2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "rotation_per_second",
          "rotations_per_second"
        )
      ),
      "symbol" => array(
        "rps",
        "cps"
      ),
      "def" => "rotation/second",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(),
      "symbol" => array(
        "rpm"
      ),
      "def" => "rotation/minute",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "denier",
          "deniers"
        )
      ),
      "symbol" => array(),
      "def" => "1.111111e-7 kg/m",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "tex",
          "texs"
        )
      ),
      "symbol" => array(),
      "def" => "1e-6 kg/m",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "perm_0C",
          "perms_0C"
        )
      ),
      "symbol" => array(),
      "def" => "5.72135e-11 kg/(Pa.s.m^2)",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "perm_23C",
          "perms_23C"
        )
      ),
      "symbol" => array(),
      "def" => "5.74525e-11 kg/(Pa.s.m^2)",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "circular_mil",
          "circular_mils"
        )
      ),
      "symbol" => array(),
      "def" => "5.067075e-10 m^2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "darcy",
          "darcys"
        )
      ),
      "symbol" => array(),
      "def" => "9.869233e-13 m^2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "acre",
          "acres"
        )
      ),
      "symbol" => array(),
      "def" => "160 rod^2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "acre_foot",
          "acre_feet"
        )
      ),
      "symbol" => array(),
      "def" => "1.233489e3 m^3",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "board_foot",
          "board_feet"
        )
      ),
      "symbol" => array(),
      "def" => "2.359737e-3 m^3",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "bushel",
          "bushels"
        )
      ),
      "symbol" => array(
        "bu"
      ),
      "def" => "3.523907e-2 m^3",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "peck",
          "pecks"
        )
      ),
      "symbol" => array(
        "pk"
      ),
      "def" => "bushel/4",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "Canadian_liquid_gallon",
          "Canadian_liquid_gallons"
        )
      ),
      "symbol" => array(),
      "def" => "4.546090e-3 m^3",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "US_dry_gallon",
          "US_dry_gallons"
        )
      ),
      "symbol" => array(),
      "def" => "4.404884e-3 m^3",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(),
      "symbol" => array(
        "cc"
      ),
      "def" => "cm^3",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "stere",
          "steres"
        )
      ),
      "symbol" => array(),
      "def" => "1 m^3",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "register_ton",
          "register_tons"
        )
      ),
      "symbol" => array(),
      "def" => "2.831685 m^3",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "US_dry_quart",
          "US_dry_quarts"
        ),
        array(
          "dry_quart",
          "dry_quarts"
        )
      ),
      "symbol" => array(),
      "def" => "US_dry_gallon/4",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "US_dry_pint",
          "US_dry_pints"
        ),
        array(
          "dry_pint",
          "dry_pints"
        )
      ),
      "symbol" => array(),
      "def" => "US_dry_gallon/8",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "US_liquid_gallon",
          "US_liquid_gallons"
        ),
        array(
          "liquid_gallon",
          "liquid_gallons"
        ),
        array(
          "gallon",
          "gallons"
        )
      ),
      "symbol" => array(),
      "def" => "3.785412e-3 m^3",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "barrel",
          "barrels"
        )
      ),
      "symbol" => array(
        "bbl"
      ),
      "def" => "42 US_liquid_gallon",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "firkin",
          "firkins"
        )
      ),
      "symbol" => array(),
      "def" => "barrel/4",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "US_liquid_quart",
          "US_liquid_quarts"
        ),
        array(
          "liquid_quart",
          "liquid_quarts"
        ),
        array(
          "quart",
          "quarts"
        )
      ),
      "symbol" => array(),
      "def" => "US_liquid_gallon/4",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "US_liquid_pint",
          "US_liquid_pints"
        ),
        array(
          "liquid_pint",
          "liquid_pints"
        ),
        array(
          "pint",
          "pints"
        )
      ),
      "symbol" => array(
        "pt"
      ),
      "def" => "US_liquid_gallon/8",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "US_liquid_cup",
          "US_liquid_cups"
        ),
        array(
          "liquid_cup",
          "liquid_cups"
        ),
        array(
          "cup",
          "cups"
        )
      ),
      "symbol" => array(),
      "def" => "US_liquid_gallon/16",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "US_liquid_gill",
          "US_liquid_gills"
        ),
        array(
          "liquid_gill",
          "liquid_gills"
        ),
        array(
          "gill",
          "gills"
        )
      ),
      "symbol" => array(),
      "def" => "US_liquid_gallon/32",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "US_fluid_ounce",
          "US_fluid_ounces"
        ),
        array(
          "US_liquid_ounce",
          "US_liquid_ounces"
        ),
        array(
          "fluid_ounce",
          "fluid_ounces"
        ),
        array(
          "liquid_ounce",
          "liquid_ounces"
        )
      ),
      "symbol" => array(
        "oz",
        "floz"
      ),
      "def" => "US_liquid_gallon/128",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "tablespoon",
          "tablespoons"
        )
      ),
      "symbol" => array(
        "Tbl",
        "Tbsp",
        "tbsp",
        "Tblsp",
        "tblsp"
      ),
      "def" => "US_fluid_ounce/2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(),
      "symbol" => array(
        "fldr"
      ),
      "def" => "US_fluid_ounce/8",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "dram",
          "drams"
        )
      ),
      "symbol" => array(
        "dr"
      ),
      "def" => "US_fluid_ounce/16",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "teaspoon",
          "teaspoons"
        )
      ),
      "symbol" => array(
        "tsp"
      ),
      "def" => "tablespoon/3",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "UK_liquid_gallon",
          "UK_liquid_gallons"
        )
      ),
      "symbol" => array(),
      "def" => "4.546090e-3 m^3",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "UK_liquid_quart",
          "UK_liquid_quarts"
        )
      ),
      "symbol" => array(),
      "def" => "UK_liquid_gallon/4",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "UK_liquid_pint",
          "UK_liquid_pints"
        )
      ),
      "symbol" => array(),
      "def" => "UK_liquid_gallon/8",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "UK_liquid_cup",
          "UK_liquid_cups"
        )
      ),
      "symbol" => array(),
      "def" => "UK_liquid_gallon/16",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "UK_liquid_gill",
          "UK_liquid_gills"
        )
      ),
      "symbol" => array(),
      "def" => "UK_liquid_gallon/32",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "UK_fluid_ounce",
          "UK_fluid_ounces"
        ),
        array(
          "UK_liquid_ounce",
          "UK_liquid_ounces"
        )
      ),
      "symbol" => array(),
      "def" => "UK_liquid_gallon/160",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(),
      "symbol" => array(
        "BZ"
      ),
      "def" => "lg(re (1e-6 m)^3)",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "shake",
          "shakes"
        )
      ),
      "symbol" => array(),
      "def" => "1e-8 s",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "sidereal_day",
          "sidereal_days"
        )
      ),
      "symbol" => array(),
      "def" => "8.616409e4 s",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "sidereal_hour",
          "sidereal_hours"
        )
      ),
      "symbol" => array(),
      "def" => "3.590170e3 s",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "sidereal_minute",
          "sidereal_minutes"
        )
      ),
      "symbol" => array(),
      "def" => "5.983617e1 s",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "sidereal_second",
          "sidereal_seconds"
        )
      ),
      "symbol" => array(),
      "def" => "0.9972696 s",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "sidereal_year",
          "sidereal_years"
        )
      ),
      "symbol" => array(),
      "def" => "3.155815e7 s",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "tropical_year",
          "tropical_years"
        ),
        array(
          "year",
          "years"
        )
      ),
      "symbol" => array(
        "yr"
      ),
      "def" => "3.15569259747e7 s",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "lunar_month",
          "lunar_months"
        )
      ),
      "symbol" => array(),
      "def" => "29.530589 day",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "common_year",
          "common_years"
        )
      ),
      "symbol" => array(),
      "def" => "365 day",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "leap_year",
          "leap_years"
        )
      ),
      "symbol" => array(),
      "def" => "366 day",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "Julian_year",
          "Julian_years"
        )
      ),
      "symbol" => array(),
      "def" => "365.25 day",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "Gregorian_year",
          "Gregorian_years"
        )
      ),
      "symbol" => array(),
      "def" => "365.2425 day",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "sidereal_month",
          "sidereal_months"
        )
      ),
      "symbol" => array(),
      "def" => "27.321661 day",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "tropical_month",
          "tropical_months"
        )
      ),
      "symbol" => array(),
      "def" => "27.321582 day",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "fortnight",
          "fortnights"
        )
      ),
      "symbol" => array(),
      "def" => "14 day",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "week",
          "weeks"
        )
      ),
      "symbol" => array(),
      "def" => "7 day",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "jiffy",
          "jiffys"
        )
      ),
      "symbol" => array(),
      "def" => "0.01 s",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "eon",
          "eons"
        )
      ),
      "symbol" => array(),
      "def" => "1e9 year",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "month",
          "months"
        )
      ),
      "symbol" => array(),
      "def" => "year/12",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "sverdrup",
          "sverdrups"
        )
      ),
      "symbol" => array(),
      "def" => "1e6 m^3/s",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "standard_free_fall",
          "standard_free_falls"
        )
      ),
      "symbol" => array(),
      "def" => "9.806650 m/s^2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "gravity",
          "gravitys"
        )
      ),
      "symbol" => array(),
      "def" => "standard_free_fall",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "conventional_water",
          "conventional_waters"
        ),
        array(
          "water",
          "waters"
        )
      ),
      "symbol" => array(
        "H2O",
        "h2o"
      ),
      "def" => "gravity 1000 kg/m^3",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "water_4C",
          "waters_4C"
        ),
        array(
          "water_39F",
          "waters_39F"
        )
      ),
      "symbol" => array(),
      "def" => "gravity 999.972 kg/m^3",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "water_60F",
          "waters_60F"
        )
      ),
      "symbol" => array(),
      "def" => "gravity 999.001 kg/m^3",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "mercury_0C",
          "mercuries_0C"
        ),
        array(
          "mercury_32F",
          "mercuries_32F"
        ),
        array(
          "conventional_mercury",
          "conventional_mercuries"
        )
      ),
      "symbol" => array(
        "Hg"
      ),
      "def" => "gravity 13595.10 kg/m^3",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "mercury_60F",
          "mercuries_60F"
        )
      ),
      "symbol" => array(),
      "def" => "gravity 13556.8 kg/m^3",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "force",
          "forces"
        )
      ),
      "symbol" => array(),
      "def" => "standard_free_fall",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "dyne",
          "dynes"
        )
      ),
      "symbol" => array(),
      "def" => "1e-5 N",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "pond",
          "ponds"
        )
      ),
      "symbol" => array(),
      "def" => "9.806650e-3 N",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "force_kilogram",
          "force_kilograms"
        ),
        array(
          "kilogram_force",
          "kilograms_force"
        )
      ),
      "symbol" => array(
        "kgf"
      ),
      "def" => "9.806650 N",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "force_ounce",
          "force_ounces"
        ),
        array(
          "ounce_force",
          "ounces_force"
        )
      ),
      "symbol" => array(
        "ozf"
      ),
      "def" => "2.780139e-1 N",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "force_pound",
          "force_pounds"
        ),
        array(
          "pound_force",
          "pounds_force"
        )
      ),
      "symbol" => array(
        "lbf"
      ),
      "def" => "4.4482216152605 N",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "poundal",
          "poundals"
        )
      ),
      "symbol" => array(),
      "def" => "1.382550e-1 N",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "gram_force",
          "grams_force"
        ),
        array(
          "force_gram",
          "force_grams"
        )
      ),
      "symbol" => array(
        "gf"
      ),
      "def" => "gram force",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "force_ton",
          "force_tons"
        ),
        array(
          "ton_force",
          "tons_force"
        )
      ),
      "symbol" => array(),
      "def" => "2000 force_pound",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "kip",
          "kips"
        )
      ),
      "symbol" => array(),
      "def" => "1000 lbf",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "standard_atmosphere",
          "standard_atmospheres"
        ),
        array(
          "atmosphere",
          "atmospheres"
        )
      ),
      "symbol" => array(
        "atm"
      ),
      "def" => "1.01325e5 Pa",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "technical_atmosphere",
          "technical_atmospheres"
        )
      ),
      "symbol" => array(
        "at"
      ),
      "def" => "1 kg gravity/cm2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(),
      "symbol" => array(
        "cm_H2O",
        "cmH2O"
      ),
      "def" => "cm H2O",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "inch_H2O_39F",
          "inches_H2O_39F"
        )
      ),
      "symbol" => array(),
      "def" => "inch water_39F",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "inch_H2O_60F",
          "inches_H2O_60F"
        )
      ),
      "symbol" => array(),
      "def" => "inch water_60F",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "foot_water",
          "feet_water"
        ),
        array(
          "foot_H2O",
          "feet_H2O"
        ),
        array(
          "footH2O",
          "feetH2O"
        )
      ),
      "symbol" => array(
        "ftH2O",
        "fth2o"
      ),
      "def" => "foot water",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(),
      "symbol" => array(
        "cm_Hg",
        "cmHg"
      ),
      "def" => "cm Hg",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "millimeter_Hg_0C",
          "millimeters_Hg_0C"
        )
      ),
      "symbol" => array(),
      "def" => "mm mercury_0C",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "inch_Hg_32F",
          "inches_Hg_32F"
        )
      ),
      "symbol" => array(),
      "def" => "inch mercury_32F",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "inch_Hg_60F",
          "inches_Hg_60F"
        )
      ),
      "symbol" => array(),
      "def" => "inch mercury_60F",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "millimeter_Hg",
          "millimeters_Hg"
        ),
        array(
          "torr",
          "torrs"
        )
      ),
      "symbol" => array(
        "mm_Hg",
        "mm_hg",
        "mmHg",
        "mmhg"
      ),
      "def" => "mm Hg",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "inch_Hg",
          "inches_Hg"
        )
      ),
      "symbol" => array(
        "in_Hg",
        "inHg",
        "in. Hg"
      ),
      "def" => "inch Hg",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(),
      "symbol" => array(
        "psi"
      ),
      "def" => "1 pound gravity/in^2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(),
      "symbol" => array(
        "ksi"
      ),
      "def" => "kip/in^2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "barie",
          "baries"
        ),
        array(
          "barye",
          "baryes"
        )
      ),
      "symbol" => array(),
      "def" => "0.1 N/m^2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(),
      "symbol" => array(
        "B_SPL"
      ),
      "def" => "lg(re 20e-6 Pa)",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "poise",
          "poises"
        )
      ),
      "symbol" => array(),
      "def" => "1e-1 Pa.s",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "stokes",
          "stokes"
        )
      ),
      "symbol" => array(
        "St"
      ),
      "def" => "1e-4 m^2/s",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "rhe",
          "rhes"
        )
      ),
      "symbol" => array(),
      "def" => "10/(Pa.s)",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "erg",
          "ergs"
        )
      ),
      "symbol" => array(),
      "def" => "1e-7 J",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "IT_Btu",
          "IT_Btus"
        ),
        array(
          "Btu",
          "Btus"
        )
      ),
      "symbol" => array(),
      "def" => "1.05505585262e3 J",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "EC_therm",
          "EC_therms"
        )
      ),
      "symbol" => array(),
      "def" => "1.05506e8 J",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "thermochemical_calorie",
          "thermochemical_calories"
        )
      ),
      "symbol" => array(),
      "def" => "4.184000 J",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "IT_calorie",
          "IT_calories"
        ),
        array(
          "calorie",
          "calories"
        )
      ),
      "symbol" => array(
        "cal"
      ),
      "def" => "4.1868 J",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "TNT",
          null
        )
      ),
      "symbol" => array(),
      "def" => "4.184 MJ/kg",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "ton_TNT",
          "tons_TNT"
        )
      ),
      "symbol" => array(),
      "def" => "4.184e9 J",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "US_therm",
          "US_therms"
        ),
        array(
          "therm",
          "therms"
        )
      ),
      "symbol" => array(
        "thm"
      ),
      "def" => "1.054804e8 J",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "watthour",
          "watthours"
        )
      ),
      "symbol" => array(),
      "def" => "watt.hour",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(),
      "symbol" => array(
        "bev"
      ),
      "def" => "1e9 eV",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "voltampere",
          "voltamperes"
        )
      ),
      "symbol" => array(
        "VA"
      ),
      "def" => "V.A",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "boiler_horsepower",
          "boiler_horsepowers"
        )
      ),
      "symbol" => array(),
      "def" => "9.80950e3 W",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "shaft_horsepower",
          "shaft_horsepowers"
        ),
        array(
          "horsepower",
          "horsepowers"
        )
      ),
      "symbol" => array(
        "hp"
      ),
      "def" => "7.456999e2 W",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "metric_horsepower",
          "metric_horsepowers"
        )
      ),
      "symbol" => array(),
      "def" => "7.35499e2 W",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "electric_horsepower",
          "electric_horsepowers"
        )
      ),
      "symbol" => array(),
      "def" => "7.460000e2 W",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "water_horsepower",
          "water_horsepowers"
        )
      ),
      "symbol" => array(),
      "def" => "7.46043e2 W",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "UK_horsepower",
          "UK_horsepowers"
        )
      ),
      "symbol" => array(),
      "def" => "7.4570e2 W",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "refrigeration_ton",
          "refrigeration_tons"
        ),
        array(
          "ton_of_refrigeration",
          "tons_of_refrigeration"
        )
      ),
      "symbol" => array(),
      "def" => "12000 Btu/hr",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(),
      "symbol" => array(
        "BW"
      ),
      "def" => "lg(re 1 W)",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(),
      "symbol" => array(
        "Bm"
      ),
      "def" => "lg(re 1 mW)",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "clo",
          "clos"
        )
      ),
      "symbol" => array(),
      "def" => "1.55e-1 K.m^2/W",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "abampere",
          "abamperes"
        )
      ),
      "symbol" => array(),
      "def" => "10 A",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "gilbert",
          "gilberts"
        )
      ),
      "symbol" => array(),
      "def" => "7.957747e-1 A",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "statampere",
          "statamperes"
        )
      ),
      "symbol" => array(),
      "def" => "3.335640e-10 A",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "biot",
          "biots"
        )
      ),
      "symbol" => array(),
      "def" => "10 A",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "abfarad",
          "abfarads"
        )
      ),
      "symbol" => array(),
      "def" => "1e9 F",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "abhenry",
          "abhenrys"
        )
      ),
      "symbol" => array(),
      "def" => "1e-9 H",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "abmho",
          "abmhos"
        )
      ),
      "symbol" => array(),
      "def" => "1e9 S",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "abohm",
          "abohms"
        )
      ),
      "symbol" => array(),
      "def" => "1e-9 ohm",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "abvolt",
          "abvolts"
        )
      ),
      "symbol" => array(),
      "def" => "1e-8 V",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(),
      "symbol" => array(
        "e"
      ),
      "def" => "1.60217733-19 C",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "chemical_faraday",
          "chemical_faradays"
        )
      ),
      "symbol" => array(),
      "def" => "9.64957e4 C",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "physical_faraday",
          "physical_faradays"
        )
      ),
      "symbol" => array(),
      "def" => "9.65219e4 C",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "C12_faraday",
          "C12_faradays"
        ),
        array(
          "faraday",
          "faradays"
        )
      ),
      "symbol" => array(),
      "def" => "9.648531e4 C",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "gamma",
          "gammas"
        )
      ),
      "symbol" => array(),
      "def" => "1e-9 T",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "gauss",
          "gauss"
        )
      ),
      "symbol" => array(),
      "def" => "1e-4 T",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "maxwell",
          "maxwells"
        )
      ),
      "symbol" => array(),
      "def" => "1e-8 Wb",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "oersted",
          "oersteds"
        )
      ),
      "symbol" => array(
        "Oe"
      ),
      "def" => "7.957747e1 A/m",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "statcoulomb",
          "statcoulombs"
        )
      ),
      "symbol" => array(),
      "def" => "3.335640e-10 C",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "statfarad",
          "statfarads"
        )
      ),
      "symbol" => array(),
      "def" => "1.112650e-12 F",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "stathenry",
          "stathenrys"
        )
      ),
      "symbol" => array(),
      "def" => "8.987554e11 H",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "statmho",
          "statmhos"
        )
      ),
      "symbol" => array(),
      "def" => "1.112650e-12 S",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "statohm",
          "statohms"
        )
      ),
      "symbol" => array(),
      "def" => "8.987554e11 ohm",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "statvolt",
          "statvolts"
        )
      ),
      "symbol" => array(),
      "def" => "2.997925e2 V",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "unit_pole",
          "unit_poles"
        )
      ),
      "symbol" => array(),
      "def" => "1.256637e-7 Wb",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(),
      "symbol" => array(
        "BV"
      ),
      "def" => "lg(re 1 V)",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(),
      "symbol" => array(
        "Bv"
      ),
      "def" => "lg(re 0.775 V)",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(),
      "symbol" => array(
        "B&#xB5;V"
      ),
      "def" => "lg(re 1e-6 V)",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "degree_rankine",
          "degrees_rankine"
        ),
        array(
          "degreeR",
          "degreesR"
        ),
        array(
          "degree_R",
          "degrees_R"
        ),
        array(
          "degR",
          "degsR"
        ),
        array(
          "deg_R",
          "degs_R"
        )
      ),
      "symbol" => array(
        "&#xB0;R"
      ),
      "def" => "K/1.8",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "degree_fahrenheit",
          "degrees_fahrenheit"
        ),
        array(
          "degreeF",
          "degreesF"
        ),
        array(
          "degree_F",
          "degrees_F"
        ),
        array(
          "degF",
          "degsF"
        ),
        array(
          "deg_F",
          "degs_F"
        )
      ),
      "symbol" => array(
        "&#xB0;F",
        "&#x2109;",
        "\xB0F"
      ),
      "def" => "degree_rankine @ 459.67",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "footcandle",
          "footcandles"
        )
      ),
      "symbol" => array(),
      "def" => "1.076391e-1 lx",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "footlambert",
          "footlamberts"
        )
      ),
      "symbol" => array(),
      "def" => "3.426259 cd/m^2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "lambert",
          "lamberts"
        )
      ),
      "symbol" => array(),
      "def" => "(1e4/pi) cd/m^2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "stilb",
          "stilbs"
        )
      ),
      "symbol" => array(
        "sb"
      ),
      "def" => "1e4 cd/m^2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "phot",
          "phots"
        )
      ),
      "symbol" => array(
        "ph"
      ),
      "def" => "1e4 lm/m^2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "nit",
          "nits"
        )
      ),
      "symbol" => array(
        "nt"
      ),
      "def" => "1 cd/m^2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "langley",
          "langleys"
        )
      ),
      "symbol" => array(),
      "def" => "4.184000e4 J/m^2",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "blondel",
          "blondels"
        ),
        array(
          "apostilb",
          "apostilbs"
        )
      ),
      "symbol" => array(),
      "def" => "cd/(pi m^2)",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "kayser",
          "kaysers"
        )
      ),
      "symbol" => array(),
      "def" => "100/m",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "geopotential",
          "geopotentials"
        ),
        array(
          "dynamic",
          "dynamics"
        )
      ),
      "symbol" => array(
        "gp"
      ),
      "def" => "gravity",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "work_year",
          "work_years"
        )
      ),
      "symbol" => array(),
      "def" => "2056 hours",
      "isBase" => false,
      "hasDimension" => true
    ),
    array(
      "name" => array(
        array(
          "work_month",
          "work_months"
        )
      ),
      "symbol" => array(),
      "def" => "work_year/12",
      "isBase" => false,
      "hasDimension" => true
    )
  );


  /**
   * Static method to convert from a unit definition to a unit definition.
   *
   * @public
   * @static
   * @param string $from Input unit definition.
   * @param string $to Output unit definition.
   * @param number $qty A number to convert $from $to.
   * @return float The converted value.
   */
  function Convert($from,$to=null,$qty=1) { 
    if( $to===null ) { 
      if( preg_match('/^\s*(?:from\s+)?(.*?)\s+to\s+(.*?)\s*$/',$from,$pts) ) { 
        $from = $pts[1];
        $to = $pts[2];
      } else if( preg_match('/^\s*(?:to\s+)?(.*?)\s+from\s+(.*?)\s*$/',$from,$pts) ) { 
        $from = $pts[2];
        $to = $pts[1];
      } else { 
        error_log("Couldn't determine `from` units `to` units.");
        return false;
      }
    }

    $u = new Units();
    $value = $u->toSI($from,$qty);
    return $u->fromSI($to,$value);
  } // END: function Convert($from,$to=null,$fmt=null)


  /**
   * Find the scale and offset coefficients to user's input.
   *
   * C = K @ 273.15;
   * F = R @ 459.67; 
   * R = 1.8/K;
   * F = 1.8/K @ 459.67;
   *
   *  F -> C :: F -> K, C <- K
   *  F -> K :: s = 1/1.8, o = 459.67 :: (212 F + 459.67) / 1.8 = 373.15 K
   *  K -> C :: s = 1, o = 273.15 :: ( 373.15 K / 1.0 ) - 273.15 = 100 C
   *
   * @public
   * @param string $from User's unit definition (non-base-units).
   * @return array(scale,offset) Amount to add to a quantity before 
   *   multiplying by the scale amount in order to obtain SI units.
   */
  function getToSI($from) {
    if( strlen($from)===0 ) { 
      return array(1,0);
    }

    // Precidence:
    // * left-to-right
    // * id, grouped, raise, product | quotient, logarithmic, offset
    // http://www.unidata.ucar.edu/software/udunits/udunits-2/udunits2lib.html#Syntax


    $float = '(?:[+-]?[0-9]*\.?[0-9]+|[0-9]+\.?[0-9]*)';
    $number_term = '(?:'.$float.'(?:[eE]'.$float.')?)';

    $shift_op = '(?:\@|after|from|since|ref)';

    //  log_10, lg_10, ln_e, lb_2
    $logref = '\b(log|lg|ln|lb)\s*\(\s*re\:?\s*';

    $alpha = '[a-zA-Z_]';
    $digit = '[0-9]';
    $alphanum = '(?:'.$alpha.'|'.$digit.')';
    $mu = '(?:\xB5|\x{3BC})';
    $deg = '(?:\xB0)';
    $id_term = '(?:(?:(?<![0-9])'.$alpha.$alphanum.'*)|%|\'|\"|'.$mu.'|'.$deg.')';
    $raise = '(?:\^|\*\*)';

    $middledot = '(?:\xB7)';
    $mul = '(?:\-|(?<![0-9])\.(?![0-9])|\*|\s+|'.$middledot.')';
    $div = '(?:per|PER|\/)';


    if( preg_match('/^'.$number_term.'$/su',$from) ) { 
      if( (string)intVal($from)===(string)$from ) { 
        return array( intVal($from), 0 );
      }
      return array( floatVal($from), 0 );
    }


    // Explicit power raise (after id replacement)
    $pat = '/\b('.$number_term.')'.$raise.'('.$number_term.')\b/su';
    if( preg_match($pat,$from,$pts) ) {
      $value = pow( $pts[1], $pts[2] );
      // $from = str_replace($pts[0],$value,$from);
      return $this->getToSI( str_replace($pts[0],$value,$from) );
    }


    if( preg_match('/^('.$number_term.')'.$mul.'('.$number_term.')\b/su',$from,$pts) ) { 
      $value = $pts[1] * $pts[2];
      // $from = str_replace($pts[0],$value,$from);
      return $this->getToSI( str_replace($pts[0],$value,$from) );
    }


    if( preg_match('/^('.$number_term.')'.$div.'('.$number_term.')\b/su',$from,$pts) ) { 
      $value = $pts[1] / $pts[2];
      // $from = str_replace($pts[0],$value,$from);
      return $this->getToSI( str_replace($pts[0],$value,$from) );
    }


    if( preg_match('/'.$logref.'/su',$from,$pts) ) { 
      // Reduce the part after 're:'
      $match_0 = strpos($from,$pts[0]);
      $match_1 = $match_0 + strlen($pts[0]);
      $subst_0 = $match_1; // preserve original offset for inner matching.
      $ct=1;
      $len = strlen($from);
      // Seek out the balance to our open parenthesis.
      while($ct>0 && $match_1 < $len) { 
        if( $from{$match_1}=='(' ) { 
          $ct += 1;
        } else if( $from{$match_1}==')' ) { 
          $ct -= 1;
        }
        $match_1 += 1;
      }
      // Perform inner matching.
      $substr = substr($from,$subst_0,$match_1-$subst_0-1);
      list($s,$o) = $this->getToSI($substr);
      $value = ($o==0) ? $s : (($s==1) ? $o : $s + $o);

      switch($pts[1]) { 
        case "log":
        case "lg":
          $value = log10( $value );
          break;
        case "ln":
          $value = log( $value );
          break;
        case "lb":
          $value = log( $value ) / log(2);
          break;
        default: 
          error_log("Unrecognized log");
          exit( 1 );
      }

      $replace = substr($from,$match_0,$match_1-$match_0);
      return $this->getToSI( str_replace($replace,$value,$from) );
    }


    if( preg_match('/\(([^\(\)]*)\)/su',$from,$pts) ) {
      if( $pts[0] === trim($from) ) { 
        return $this->getToSI($pts[1]);
      }
      list($s,$o) = $this->getToSI($pts[1]);
      $value = ($o==0) ? $s : "$s @ $o";
      return $this->getToSI( str_replace($pts[0],$value,$from) );
    }


    $pat = '/\b('.$id_term.')('.$number_term.')\b/su';
    if( preg_match($pat,$from,$pts) ) {
      $value = $this->getValueFromId($pts[1]);
      if( $value === false ) { 
        error_log("Unrecognized terminal: ".$pts[1]);
        return false;
      }

      list($s,$o) = $this->getToSI($value);
      $value = ($o==0) ? $s : (($s==1) ? $o : $s + $o);
      $value = pow( $value , $pts[2] );
      return $this->getToSI( str_replace($pts[0],$value,$from) );
    }

    if( preg_match('/('.$id_term.')/su',$from,$pts) ) {
      $value = $this->getValueFromId($pts[1]);
      if( $value === false ) { 
        error_log("Unrecognized terminal: ".$pts[1]);
        return false;
      }
      return $this->getToSI( str_replace($pts[0],"($value)",$from) );
    }

    // example: degree_Celsius.def = K @ 273.15
    if( preg_match('/^(.*?)\s*'.$shift_op.'\s*('.$number_term.')$/su',$from,$pts) ) {
      list($s,$o) = $this->getToSI($pts[1]);
      return array($s,$o + $pts[2]*$s);
    }


    return $this->getToSI($from);
  } // END: function getToSI($from)


  /**
   * Find and apply the scale and offset coefficients to user's input.
   *
   * @public
   * @param string $from What to convert SI unit from (from mile to SI? $from="mile")
   * @param float $qty Number value in SI units to convert to $to units.
   * @return float The converted $qty, from $from to SI units.
   */
  function toSI($from,$qty=1) { 
    $float = '(?:[+-]?[0-9]*\.?[0-9]+|[0-9]+\.?[0-9]*)';
    $number_term = '(?:'.$float.'(?:[eE]'.$float.')?)';
    if( preg_match('/^\s*('.$number_term.')\s*/su',$from,$pts) ) { 
      $qty *= $pts[1];
      $from = str_replace($pts[0],'',$from);
    }

    list($scale,$offset) = $this->getToSI($from);
    return ($qty * $scale) + $offset;
    // return ($qty + $offset) * $scale;
  } // END: function toSI($from,$qty=1)


  /**
   * Find and apply the scale and offset coefficients to user's input.
   *
   * @public
   * @param string $to What to convert SI unit to (meter to mile? $to="mile")
   * @param float $qty Number value in SI units to convert to $to units.
   * @return float The converted $qty, from SI to $to units.
   */
  function fromSI($to,$qty=1) { 
    $float = '(?:[+-]?[0-9]*\.?[0-9]+|[0-9]+\.?[0-9]*)';
    $number_term = '(?:'.$float.'(?:[eE]'.$float.')?)';
    if( preg_match('/^\s*('.$number_term.')\s*/su',$to,$pts) ) { 
      $qty *= $pts[1];
      $from = str_replace($pts[0],'',$to);
    }

    list($scale,$offset) = $this->getToSI($to);
    return ($qty - $offset)/$scale;
    // return $qty/$scale - $offset;
  } // END: function fromSI($to,$qty=1)


  /**
   * Determine if unit's name is the plural name (foot = true)
   *
   * @public
   * @return bool
   */
  function isSingular($unit_name) {
    $s = $this->getSingular($unit_name);
    return ( $s && strcasecmp($s,$unit_name)===0 );
  } // END: function isSingular($unit_name)


  /**
   * Determine if unit's name is the plural form (feet = true)
   *
   * @public
   * @return bool
   */
  function isPlural($unit_name) {
    $p = $this->getPlural($unit_name);
    return ( $p && strcasecmp($p,$unit_name)===0 );
  } // END: function isPlural($unit_name)


  /**
   * Determine if unit has a plural name (Celsius = false)
   *
   * @public
   * @return bool
   */
  function hasPlural($unit_name) {
    $p = $this->getPlural($unit_name);
    return ( $p!==false && $p!==null );
  } // END: function hasPlural($unit_name)


  /**
   * Return the singular for a unit (eg: "feet" => foot)
   *
   * @public
   * @return string A unit's singular
   */
  function getSingular($unit_name) {
    foreach( $this->units as $unit ) { 
      foreach( $unit["name"] as $name ) {
        if( strcasecmp($name[0],$unit_name)===0 
        ||  strcasecmp($name[1],$unit_name)===0 ) {
          return $name[0];
        }
      }
    }
    return false;
  } // END: function getSingular($unit_name)


  /**
   * Return the plural for a unit (eg: "meter" => meters)
   *
   * @public
   * @return string A unit's plural
   */
  function getPlural($unit_name) {
    foreach( $this->units as $unit ) { 
      foreach( $unit["name"] as $name ) {
        if( strcasecmp($name[0],$unit_name)===0 
        ||  strcasecmp($name[1],$unit_name)===0 ) {
          return $name[1];
        }
      }
    }
    return false;
  } // END: function getPlural($unit_name)



  /**
   * Return the symbol for a unit (eg: "meter" => m)
   *
   * @public
   * @return string A unit symbol
   */
  function getUnitSymbol($unit_name) {
    foreach( $this->units as $unit ) { 
      foreach( $unit["name"] as $name ) {
        if( strcasecmp($name[0],$unit_name)===0 
        ||  strcasecmp($name[1],$unit_name)===0 ) {
          if( count($unit["symbol"]) > 0 ) { 
            return $unit["symbol"][0];
          }
          return null;
        }
      }
    }
    return false;
  } // END: function getUnitSymbol($unit_name)


  /**
   * Return the symbol for an exponential prefix (eg: "milli" => m)
   *
   * @private
   * @return string An exponent's prefix
   */
  function getPrefixSymbol($prefix_name) {
    foreach( $this->prefixes as $prefix ) { 
      if( strcasecmp($prefix["name"],$prefix_name)===0 ) { 
        return $prefix["symbol"];
      }
    }
    return false;
  } // END: function getPrefixSymbol($prefix_name)


  /**
   * Determine if the unit is an SI base unit.
   *
   * @public
   * @return bool
   */
  function isBase($unit_name) {
    foreach( $this->units as $unit ) { 
      foreach( $unit["name"] as $name ) { 
        if( strcasecmp($name[0],$unit_name)===0 
        ||  strcasecmp($name[1],$unit_name)===0 ) {
          return $unit["isBase"];
        }
      }
      foreach( $unit["symbol"] as $symbol ) {
        if( $symbol === $unit_name ) { 
          return $unit["isBase"];
        }
      }
    }
    return false;
  } // END: function isBase($unit_name|$unit_symbol)


  /**
   * Not all units have dimensions (eg: percent)
   *
   * @public
   * @return bool
   */
  function hasDimension($unit_name) {
    foreach( $this->units as $unit ) { 
      foreach( $unit["name"] as $name ) {
        if( strcasecmp($name[0],$unit_name)===0 
        ||  strcasecmp($name[1],$unit_name)===0 ) {
          return $unit["hasDimension"];
        }
      }
    }
    return false;
  } // END: function hasDimension($unit_name)


  /**
   * Return the unit record for a unit name ("feet" => foot)
   *
   * @private
   * @return hash A unit record.
   */
  function findUnitByName($unit_name) { 
    foreach( $this->units as $unit ) { 
      foreach( $unit["name"] as $name ) {
        if( strcasecmp($name[0],$unit_name)===0 
        ||  strcasecmp($name[1],$unit_name)===0 ) {
          return $unit;
        }
      }
    }
    return false;
  } // END: function findUnitByName($unit_name)


  /**
   * Return the unit record for a unit symbol ("ft" => foot)
   *
   * @private
   * @return hash A unit record.
   */
  function findUnitBySymbol($unit_symbol) { 
    foreach( $this->units as $unit ) { 
      foreach( $unit["symbol"] as $symbol ) {
        if( $symbol === $unit_symbol ) { 
          return $unit;
        }
      }
    }
    return false;
  } // END: function findUnitBySymbol($unit_symbol)


  /**
   * Return the exponential record for a standard prefix (ie: "milli")
   *
   * @private
   * @return hash A prefix record.
   */
  function findPrefixByName($prefix_name) { 
    foreach( $this->prefixes as $prefix ) { 
      if( strcasecmp($prefix["name"],$prefix_name)===0 ) { 
        return $prefix;
      }
    }
    return false;
  } // END: function findPrefixByName($unit_name)


  /**
   * Return the exponential record for a standard prefix (ie: "m", for milli)
   *
   * @private
   * @return hash A prefix record.
   */
  function findPrefixBySymbol($prefix_symbol) { 
    foreach( $this->prefixes as $prefix ) { 
      if( $prefix["symbol"] === $prefix_symbol ) { 
        return $prefix;
      }
    }
    return false;
  } // END: function findPrefixBySymbol($unit_symbol)


  /**
   * Replace a prefixed ID with its exponent multiplier and core ID
   *
   * @private
   * @return string An altered definition
   */
  function applyPrefix($str) {
    foreach($this->prefixes as $prefix) {
      if( strpos($str,$prefix["name"])===0 ) { 
        $rem = substr($str, strlen($prefix["name"]));
        if( $this->findUnitByName( $rem ) ) {
          return $prefix["value"].' '.$rem;
        }
      }
      if( strpos($str,$prefix["symbol"])===0 ) {
        $rem = substr($str, strlen($prefix["symbol"]));
        if( $this->findUnitBySymbol( $rem ) ) {
          return $prefix["value"].' '.$rem;
        }
      }
    }
    // error_log("Unresolved prefix: $str");
    return $str;
  } // END: function applyPrefix($str)


  /**
   * Replace an ID with its conversion definition.
   *
   * @private
   * @return string Altered input string.
   */
  function applyDefinition($str) {
    $found = $this->findUnitBySymbol($str);
    if( !$found ) { 
      $found = $this->findUnitByName($str);
    }
    if( $found ) { 
      if( $found["isBase"] || !$found["hasDimension"] ) { 
        return 1;
      }
      if( $found["def"] ) {
        return preg_replace('/(?<=[a-z])\.(?=[a-z])/i',' ',$found["def"]);
      }
    }
    // error_log("Unresoved definition: $str");
    return $str;
  } // END: function applyDefinition($str)


  /**
   * Convert a unit identifier to it's ToSI equivalent value, or alternate def
   * @private
   * @return string Upconverted ID
   */
  function getValueFromId($str) {
    $val = 1;
    $id = $str;
    
    $defined = $this->applyDefinition($str);
    if( $defined == $str ) { 
      $defined = $this->applyPrefix($str);
    }
    return ($defined===$str) ? false : $defined;
  } // END: function getValueForId($str)


} // END: class Units


/*
$u = new Units();
print_r( $u->getToSI("900 cm/hr") );

$u = new Units();
$sym_C = $u->getUnitSymbol('deg_C');
$sym_F = $u->getUnitSymbol('deg_F');

$deg_C = Units::Convert("212 deg_F","deg_C");
printf("212 %s = %f %s\n",$sym_F, $deg_C, $sym_C);
$deg_C = Units::Convert("deg_F","deg_C",212);
printf("%f %s\n",$deg_C, $sym_C);
$deg_C = Units::Convert("212 deg_F to deg_C");
printf("%f %s\n",$deg_C, $sym_C);
echo("--\n");

// Convert from "12 inches" to SI to "cm":
$u = new Units();
$value_m  = $u->toSI("12 inches");
printf("%f m\n",$value_m);
$value_cm = $u->fromSI("cm",$value_m);
printf("%f cm\n",$value_cm);
$value_in = Units::Convert("cm","inches",$value_cm);
printf("%f inches\n",$value_in);
echo("--\n");

// Convert from deg_F to deg_C for bulk callback:
$u = new Units();
list($s_f,$o_f) = $u->getToSI("deg_F");
list($s_t,$o_t) = $u->getToSI("deg_C");
$value = (((212 * $s_f) + $o_f) - $o_t) / $s_t;
echo("\$value = (((212 * \$s_f) + \$o_f) - \$o_t) / \$s_t;\n");
echo("$value = (((212 * $s_f) + $o_f) - $o_t) / $s_t;\n");
// $value = (((212 + $o_f) * $s_f) / $s_t) - $o_t;
printf("%f %s\n",$value, $sym_C);
echo("--\n");
*/

// EOF -- Units.php
?>
