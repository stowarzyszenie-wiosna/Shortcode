<?php
namespace Thunder\Shortcode\Tests;

use Thunder\Shortcode\Parser\RegularParser;
use Thunder\Shortcode\Parser\ParserInterface;
use Thunder\Shortcode\Parser\RegexParser;
use Thunder\Shortcode\Parser\WordpressParser;
use Thunder\Shortcode\Shortcode\ParsedShortcode;
use Thunder\Shortcode\Shortcode\Shortcode;
use Thunder\Shortcode\Syntax\CommonSyntax;
use Thunder\Shortcode\Syntax\Syntax;

/**
 * @author Tomasz Kowalczyk <tomasz@kowalczyk.cc>
 */
final class ParserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param ParserInterface $parser
     * @param string $code
     * @param ParsedShortcode[] $tested
     *
     * @dataProvider provideShortcodes
     */
    public function testParser(ParserInterface $parser, $code, array $tested)
    {
        $parsed = $parser->parse($code);

        $count = count($tested);
        $this->assertSame($count, count($parsed), 'counts');
        for ($i = 0; $i < $count; $i++) {
            $this->assertSame($tested[$i]->getName(), $parsed[$i]->getName(), 'name');
            $this->assertSame($tested[$i]->getParameters(), $parsed[$i]->getParameters(), 'parameters');
            $this->assertSame($tested[$i]->getContent(), $parsed[$i]->getContent(), 'content');
            $this->assertSame($tested[$i]->getText(), $parsed[$i]->getText(), 'text');
            $this->assertSame($tested[$i]->getOffset(), $parsed[$i]->getOffset(), 'offset');
            $this->assertSame($tested[$i]->getBbCode(), $parsed[$i]->getBbCode(), 'bbCode');
        }
    }

    public function provideShortcodes()
    {
        $s = new CommonSyntax();

        $tests = array(
            // invalid
            array($s, '', array()),
            array($s, '[/y]', array()),
            array($s, '[sc', array()),
            array($s, '[sc / [/sc]', array()),
            array($s, '[sc arg="val', array()),

            // single shortcodes
            array($s, '[sc]', array(
                new ParsedShortcode(new Shortcode('sc', array(), null), '[sc]', 0),
            )),
            array($s, '[sc arg=val]', array(
                new ParsedShortcode(new Shortcode('sc', array('arg' => 'val'), null), '[sc arg=val]', 0),
            )),
            array($s, '[sc novalue arg="complex value"]', array(
                new ParsedShortcode(new Shortcode('sc', array('novalue' => null, 'arg' => 'complex value'), null), '[sc novalue arg="complex value"]', 0),
            )),
            array($s, '[sc x="ąćęłńóśżź ĄĆĘŁŃÓŚŻŹ"]', array(
                new ParsedShortcode(new Shortcode('sc', array('x' => 'ąćęłńóśżź ĄĆĘŁŃÓŚŻŹ'), null), '[sc x="ąćęłńóśżź ĄĆĘŁŃÓŚŻŹ"]', 0),
            )),
            array($s, '[sc x="multi'."\n".'line"]', array(
                new ParsedShortcode(new Shortcode('sc', array('x' => 'multi'."\n".'line'), null), '[sc x="multi'."\n".'line"]', 0),
            )),
            array($s, '[sc noval x="val" y]content[/sc]', array(
                new ParsedShortcode(new Shortcode('sc', array('noval' => null, 'x' => 'val', 'y' => null), 'content'), '[sc noval x="val" y]content[/sc]', 0),
            )),
            array($s, '[sc x="{..}"]', array(
                new ParsedShortcode(new Shortcode('sc', array('x' => '{..}'), null), '[sc x="{..}"]', 0),
            )),
            array($s, '[sc a="x y" b="x" c=""]', array(
                new ParsedShortcode(new Shortcode('sc', array('a' => 'x y', 'b' => 'x', 'c' => ''), null), '[sc a="x y" b="x" c=""]', 0),
            )),
            array($s, '[sc a="a \"\" b"]', array(
                new ParsedShortcode(new Shortcode('sc', array('a' => 'a \"\" b'), null), '[sc a="a \"\" b"]', 0),
            )),
            array($s, '[sc/]', array(
                new ParsedShortcode(new Shortcode('sc', array(), null), '[sc/]', 0),
            )),
            array($s, '[sc    /]', array(
                new ParsedShortcode(new Shortcode('sc', array(), null), '[sc    /]', 0),
            )),
            array($s, '[sc arg=val cmp="a b"/]', array(
                new ParsedShortcode(new Shortcode('sc', array('arg' => 'val', 'cmp' => 'a b'), null), '[sc arg=val cmp="a b"/]', 0),
            )),
            array($s, '[sc x y   /]', array(
                new ParsedShortcode(new Shortcode('sc', array('x' => null, 'y' => null), null), '[sc x y   /]', 0),
            )),
            array($s, '[sc  x="\ "   /]', array(
                new ParsedShortcode(new Shortcode('sc', array('x' => '\ '), null), '[sc  x="\ "   /]', 0),
            )),
            array($s, '[   sc   x =  "\ "   y =   value  z   /    ]', array(
                new ParsedShortcode(new Shortcode('sc', array('x' => '\ ', 'y' => 'value', 'z' => null), null), '[   sc   x =  "\ "   y =   value  z   /    ]', 0),
            )),
            array($s, '[ sc   x=  "\ "   y    =value   ] vv [ /  sc  ]', array(
                new ParsedShortcode(new Shortcode('sc', array('x' => '\ ', 'y' => 'value'), ' vv '), '[ sc   x=  "\ "   y    =value   ] vv [ /  sc  ]', 0),
            )),
            array($s, '[sc url="http://giggle.com/search" /]', array(
                new ParsedShortcode(new Shortcode('sc', array('url' => 'http://giggle.com/search'), null), '[sc url="http://giggle.com/search" /]', 0),
            )),

            // bbcode
            array($s, '[sc   =   "http://giggle.com/search" /]', array(
                new ParsedShortcode(new Shortcode('sc', array(), null, 'http://giggle.com/search'), '[sc   =   "http://giggle.com/search" /]', 0),
            )),

            // multiple shortcodes
            array($s, 'Lorem [ipsum] random [code-code arg=val] which is here', array(
                new ParsedShortcode(new Shortcode('ipsum', array(), null), '[ipsum]', 6, array('name' => 1)),
                new ParsedShortcode(new Shortcode('code-code', array('arg' => 'val'), null), '[code-code arg=val]', 21),
            )),
            array($s, 'x [aa] x [aa] x', array(
                new ParsedShortcode(new Shortcode('aa', array(), null), '[aa]', 2),
                new ParsedShortcode(new Shortcode('aa', array(), null), '[aa]', 9),
            )),
            array($s, 'x [x]a[/x] x [x]a[/x] x', array(
                new ParsedShortcode(new Shortcode('x', array(), 'a'), '[x]a[/x]', 2),
                new ParsedShortcode(new Shortcode('x', array(), 'a'), '[x]a[/x]', 13),
            )),
            array($s, 'x [x x y=z a="b c"]a[/x] x [x x y=z a="b c"]a[/x] x', array(
                new ParsedShortcode(new Shortcode('x', array('x' => null, 'y' => 'z', 'a' => 'b c'), 'a'), '[x x y=z a="b c"]a[/x]', 2),
                new ParsedShortcode(new Shortcode('x', array('x' => null, 'y' => 'z', 'a' => 'b c'), 'a'), '[x x y=z a="b c"]a[/x]', 27),
            )),
            array($s, 'x [code /] y [code]z[/code] x [code] y [code/] a', array(
                new ParsedShortcode(new Shortcode('code', array(), null), '[code /]', 2),
                new ParsedShortcode(new Shortcode('code', array(), 'z'), '[code]z[/code]', 13),
                new ParsedShortcode(new Shortcode('code', array(), null), '[code]', 30),
                new ParsedShortcode(new Shortcode('code', array(), null), '[code/]', 39),
            )),
            array($s, 'x [code arg=val /] y [code cmp="xx"/] x [code x=y/] a', array(
                new ParsedShortcode(new Shortcode('code', array('arg' => 'val'), null), '[code arg=val /]', 2),
                new ParsedShortcode(new Shortcode('code', array('cmp' => 'xx'), null), '[code cmp="xx"/]', 21),
                new ParsedShortcode(new Shortcode('code', array('x' => 'y'), null), '[code x=y/]', 40),
            )),
            array($s, 'x [    code arg=val /]a[ code/]c[x    /    ] m [ y ] c [   /   y]', array(
                new ParsedShortcode(new Shortcode('code', array('arg' => 'val'), null), '[    code arg=val /]', 2),
                new ParsedShortcode(new Shortcode('code', array(), null), '[ code/]', 23),
                new ParsedShortcode(new Shortcode('x', array(), null), '[x    /    ]', 32),
                new ParsedShortcode(new Shortcode('y', array(), ' c '), '[ y ] c [   /   y]', 47),
            )),

            // other syntax
            array(new Syntax('[[', ']]', '//', '==', '""'), '[[code arg==""val oth""]]cont[[//code]]', array(
                new ParsedShortcode(new Shortcode('code', array('arg' => 'val oth'), 'cont'), '[[code arg==""val oth""]]cont[[//code]]', 0),
            )),
            array(new Syntax('^', '$', '&', '!!!', '@@'), '^code a!!!@@\"\"@@ b!!!@@x\"y@@ c$cnt^&code$', array(
                new ParsedShortcode(new Shortcode('code', array('a' => '\"\"', 'b' => 'x\"y', 'c' => null), 'cnt'), '^code a!!!@@\"\"@@ b!!!@@x\"y@@ c$cnt^&code$', 0),
            )),
        );

        /**
         * WordPress can't handle:
         *   - incorrect shortcode opening tag (blindly matches everything
         *     between opening token and closing token)
         *   - spaces between shortcode open tag and its name ([  name]),
         *   - spaces around BBCode part ([name  = "bbcode"]),
         *   - escaped tokens anywhere in the arguments ([x arg=" \" "]),
         *   - configurable syntax (that's intended).
         *
         * Tests cases from array above with identifiers in the array below must be skipped.
         */
        $wordpressSkip = array(3, 13, 18, 19, 20, 22, 29, 30, 31);
        $result = array();
        foreach($tests as $key => $test) {
            $syntax = array_shift($test);

            $result[] = array_merge(array(new RegexParser($syntax)), $test);
            $result[] = array_merge(array(new RegularParser($syntax)), $test);
            if(!in_array($key, $wordpressSkip)) {
                $result[] = array_merge(array(new WordpressParser()), $test);
            }
        }

        return $result;
    }

    public function testWordPress()
    {
        $parser = new WordpressParser();

        $this->testParser($parser, '[code arg="<html" oth=\'val\']', array(
            new ParsedShortcode(new Shortcode('code', array('arg' => '', 'oth' => 'val'), null), '[code arg="<html" oth=\'val\']', 0)
        ));
        $this->testParser($parser, '[code "xxx"]', array(
            new ParsedShortcode(new Shortcode('code', array('xxx' => null), null, null), '[code "xxx"]', 0)
        ));
    }

    public function testInstances()
    {
        $this->assertInstanceOf('Thunder\Shortcode\Parser\WordPressParser', new WordpressParser());
        $this->assertInstanceOf('Thunder\Shortcode\Parser\RegularParser', new RegularParser());
    }
}
