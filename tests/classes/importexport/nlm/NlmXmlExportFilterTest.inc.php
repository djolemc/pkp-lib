<?php

/**
 * @file tests/classes/importexport/nlm/NlmXmlExportFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationParserServiceTest
 * @ingroup tests_classes_importexport_nlm
 * @see NlmXmlExportFilter
 *
 * @brief Tests for the NlmXmlExportFilterTest class.
 */

import('lib.pkp.tests.PKPTestCase');

import('lib.pkp.classes.core.PKPRouter');
import('lib.pkp.classes.core.PKPRequest');

import('lib.pkp.classes.importexport.nlm.NlmXmlExportFilter');

class NlmXmlExportFilterTest extends PKPTestCase {
	protected function setUp() {
		$application =& PKPApplication::getApplication();
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$request =& $application->getRequest();
		if (is_null($request->getRouter())) {
			$router = new PKPRouter();
			$request->setRouter($router);
		}
	}

	/**
	 * @covers NlmXmlExportFilter
	 */
	public function testExecute() {
		// FIXME: Change this to MetadataDescription input!
		$inputArray = array(
			'<element-citation publication-type="book"><source>Mania de bater: a punição corporal doméstica de crianças e adolescentes no Brasil</source><year>2001</year><volume>10</volume><series>Edição Standard Brasileira das Obras Psicológicas</series><size>368</size><publisher-loc>São Paulo</publisher-loc><publisher-name>Iglu</publisher-name></element-citation>',
			'<element-citation publication-type="book"><person-group person-group-type="author"><name><surname>Azevedo</surname><given-names>Mario Antonio</given-names></name></person-group><source>Mania de bater: a punição corporal doméstica de crianças e adolescentes no Brasil</source><year>2001</year><volume>10</volume><series>Edição Standard Brasileira das Obras Psicológicas</series><size>368</size><publisher-loc>São Paulo</publisher-loc><publisher-name>Iglu</publisher-name></element-citation>',
			'<element-citation publication-type="book"><person-group person-group-type="author"><name><surname>Azevedo</surname><given-names>Mario Antonio</given-names></name><name><surname>Guerra</surname><given-names>Vitor</given-names></name></person-group><source>Mania de bater: a punição corporal doméstica de crianças e adolescentes no Brasil</source><year>2001</year><volume>10</volume><chapter-title>Psicologia genética e lógica</chapter-title><series>Edição Standard Brasileira das Obras Psicológicas</series><fpage>15</fpage><lpage>25</lpage><size>368</size><publisher-loc>São Paulo</publisher-loc><publisher-name>Iglu</publisher-name></element-citation>',
			'<element-citation publication-type="book"><person-group person-group-type="author"><name><surname>Azevedo</surname><given-names>Mario Antonio</given-names></name><name><surname>Guerra</surname><given-names>Vitor</given-names></name></person-group><person-group person-group-type="editor"><name><surname>Banks-Leite</surname><given-names>Lorena</given-names></name></person-group><source>Mania de bater: a punição corporal doméstica de crianças e adolescentes no Brasil</source><year>2001</year><volume>10</volume><chapter-title>Psicologia genética e lógica</chapter-title><series>Edição Standard Brasileira das Obras Psicológicas</series><fpage>15</fpage><lpage>25</lpage><size>368</size><publisher-loc>São Paulo</publisher-loc><publisher-name>Iglu</publisher-name></element-citation>',
			'<element-citation publication-type="book"><person-group person-group-type="author"><name><surname>Azevedo</surname><given-names>Mario Antonio</given-names></name><name><surname>Guerra</surname><given-names>Vitor</given-names></name></person-group><person-group person-group-type="editor"><name><surname>Banks-Leite</surname><given-names>Lorena</given-names></name><name><surname>Velado</surname><given-names>Mariano</given-names><suffix>Jr</suffix></name></person-group><source>Mania de bater: a punição corporal doméstica de crianças e adolescentes no Brasil</source><year>2001</year><volume>10</volume><chapter-title>Psicologia genética e lógica</chapter-title><edition>2nd ed</edition><series>Edição Standard Brasileira das Obras Psicológicas</series><fpage>15</fpage><lpage>25</lpage><size>368</size><publisher-loc>São Paulo</publisher-loc><publisher-name>Iglu</publisher-name></element-citation>',
			'<element-citation publication-type="journal"><person-group person-group-type="author"><name><surname>Silva</surname><given-names>Vitor Antonio</given-names></name><name><surname>Santos</surname><given-names>Pedro</given-names><prefix>dos</prefix></name></person-group><article-title>Etinobotânica Xucuru: espécies místicas</article-title><source>Biotemas</source><month>6</month><year>2000</year><issue>1</issue><volume>15</volume><fpage>45</fpage><lpage>57</lpage><publisher-loc>Florianópolis</publisher-loc><pub-id pub-id-type="doi">10146:55793-493</pub-id><pub-id pub-id-type="pmid">12140307</pub-id></element-citation>',
			'<element-citation publication-type="journal"><person-group person-group-type="author"><name><surname>Silva</surname><given-names>Vitor Antonio</given-names></name><name><surname>Santos</surname><given-names>Pedro</given-names><prefix>dos</prefix></name><name><surname>Miller</surname><given-names>F H</given-names></name><name><surname>Choi</surname><given-names>M J</given-names></name><name><surname>Angeli</surname><given-names>L L</given-names></name><name><surname>Harland</surname><given-names>A A</given-names></name><name><surname>Stamos</surname><given-names>J A</given-names></name><name><surname>Thomas</surname><given-names>S T</given-names></name></person-group><article-title>Etinobotânica Xucuru: espécies místicas</article-title><source>Biotemas</source><month>6</month><year>2000</year><issue>1</issue><volume>15</volume><fpage>45</fpage><lpage>57</lpage><publisher-loc>Florianópolis</publisher-loc><pub-id pub-id-type="doi">10146:55793-493</pub-id><pub-id pub-id-type="pmid">12140307</pub-id></element-citation>',
			'<element-citation publication-type="conf-proc"><person-group person-group-type="author"><name><surname>Liu</surname><given-names>Sen</given-names></name></person-group><article-title>Defending against business crises with the help of intelligent agent based early warning solutions</article-title><month>5</month><year>2005</year><date-in-citation content-type="access-date"><day>12</day><month>8</month><year>2006</year></date-in-citation><conf-loc>Miami, FL</conf-loc><conf-name>The Seventh International Conference on Enterprise Information Systems</conf-name><uri>http://www.iceis.org/iceis2005/abstracts_2005.htm</uri></element-citation>'
		);
		$filter = new NlmXmlExportFilter();
		self::assertEquals('PING', $filter->execute($inputArray));
	}
}
?>
