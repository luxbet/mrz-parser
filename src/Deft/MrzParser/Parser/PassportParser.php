<?php

namespace Deft\MrzParser\Parser;

use Deft\MrzParser\Exception\ParseException;
use Deft\MrzParser\Exception\ChecksumException;
use Deft\MrzParser\Document\TravelDocument;
use Deft\MrzParser\Document\TravelDocumentInterface;
use Deft\MrzParser\Document\TravelDocumentType;

/**
 * Parser of Passport MRZ strings. The machine readable zone on a passport has
 * 2 lines, each consisting of 44 characters. Below a reference to the format:
 *   01 - 02: Document code
 *   03 - 05: Issuing state or organisation
 *   06 - 44: Names
 *   45 - 53: Document number
 *   54 - 54: Check digit
 *   55 - 57: Nationality
 *   58 - 63: Date of birth
 *   64 - 64: Check digit
 *   65 - 65: Sex
 *   66 - 71: Date of expiry
 *   72 - 72: Check digit
 *   73 - 86: Personal number
 *   87 - 87: Check digit
 *   88 - 88: Check digit
 *
 * @package Deft\MrzParser
 */
class PassportParser extends AbstractParser
{
    /**
     * Extracts all the information from a MRZ string and returns a populated instance of TravelDocumentInterface
     *
     * @param $string
     * @return TravelDocumentInterface
     * @throws ParseException|ChecksumException
     */
    public function parse($string)
    {
        if ($this->getToken($string, 1) != 'P') {
            throw new ParseException("First character is not 'P'");
        }

		$this->validate_checksum($string);

        $fields = array(
            'type' => TravelDocumentType::PASSPORT,
            'issuingCountry' => $this->getToken($string, 3, 5),
            'documentNumber' => $this->getToken($string, 45, 53),
            'nationality' => $this->getToken($string, 55, 57),
            'dateOfBirth' => $this->getDateToken($string, 58),
            'sex' => $this->getToken($string, 65),
            'dateOfExpiry' => $this->getDateToken($string, 66),
            'personalNumber' => $this->getToken($string, 73, 86)
		);

        $names = $this->getNames($string, 6, 44);
        $fields['primaryIdentifier'] = $names[0];
        $fields['secondaryIdentifier'] = $names[1];

        return new TravelDocument($fields);
    }

	/**
	 * Validate checksum
	 * based on https://en.wikipedia.org/wiki/Machine-readable_passport
	 *
	 * @param $string
	 * @throws ChecksumException
	 */
	public function validate_checksum($string)
	{
		$check_sum_passport_number = $this->checksum($this->getToken($string, 45, 53));
		$check_sum_passport_dob = $this->checksum($this->getToken($string, 58, 63));
		$check_sum_passport_expiry_date = $this->checksum($this->getToken($string, 66, 71));
		$check_sum_passport_personal_number = $this->checksum($this->getToken($string, 73, 86));

		$check_sum_passport_all = $this->checksum(
			$this->getToken($string, 45, 54) .
			$this->getToken($string, 58, 64) .
			$this->getToken($string, 66, 72)
		);

		if ($check_sum_passport_number != $this->getToken($string, 54)) {
			throw new ChecksumException("Wrong checksum for passport number");
		}

		if ($check_sum_passport_dob != $this->getToken($string, 64)) {
			throw new ChecksumException("Wrong checksum for passport DOB");
		}

		if ($check_sum_passport_expiry_date != $this->getToken($string, 72)) {
			throw new ChecksumException("Wrong checksum for passport expiry date");
		}

		if ($check_sum_passport_personal_number != $this->getToken($string, 87)) {
			throw new ChecksumException("Wrong checksum for passport personal number");
		}

		if ($check_sum_passport_all != $this->getToken($string, 88)) {
			throw new ChecksumException("Wrong checksum for passport overall checksum");
		}
	}

	/**
	 * Look up and convert character into its value
	 *
	 * @param string $char
	 * @return int
	 */
	protected function get_value($char) {
		$ascii_value = ord($char);

		if ($ascii_value >= 48 && $ascii_value <= 57) { // 0 - 9: 0 - 9
			return $ascii_value - 48;
		} else if ($ascii_value >= 65 && $ascii_value <= 90) { // A - Z: 10 - 35
			return $ascii_value - 55;
		} else if ($ascii_value == 60) { // <: 0
			return 0;
		} else {
			// this is invalid case
			return -1;
		}
	}

	/**
	 * Calculate checksum for the given string
	 *
	 * For details: http://www.highprogrammer.com/alan/numbers/mrp.html#checkdigit
	 *
	 * @param $s
	 * @return int
	 * @throws \Exception
	 */
	protected function checksum($s) {
		$count = strlen($s);

		$weights = array(7, 3, 1);

		$sum = 0;
		for ($i = 0; $i < $count; $i++) {
			$value = $this->get_value($s[$i]);

			if ($value < 0) {
				throw new ChecksumException('Invalid character (' . $value . ') on position ' . ($i + 1));
			}

			$sum += $this->get_value($s[$i]) * $weights[$i % 3];
		}

		return $sum % 10;
	}
}
