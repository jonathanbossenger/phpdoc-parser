<?php

namespace WP_Parser;

use phpDocumentor\Reflection;
use phpDocumentor\Reflection\FileReflector;

/**
 * Reflection class for a full file.
 *
 * Extends the FileReflector from phpDocumentor to parse out WordPress
 * hooks and note function relationships.
 */
class File_Reflector extends FileReflector {
	/**
	 * List of elements used in global scope in this file, indexed by element type.
	 *
	 * @var array {
	 *      @type Hook_Reflector[] $hooks     The action and filters.
	 *      @type Function_Call_Reflector[] $functions The functions called.
	 * }
	 */
	public $uses = array();

	/**
	 * List of elements used in the current class scope, indexed by method.
	 *
	 * @var array[][] {@see \WP_Parser\File_Reflector::$uses}
	 */
	protected $method_uses_queue = array();

	/**
	 * Stack of classes/methods/functions currently being parsed.
	 *
	 * @see \WP_Parser\FileReflector::getLocation()
	 * @var \phpDocumentor\Reflection\BaseReflector[]
	 */
	protected $location = array();

	/**
	 * Last DocBlock associated with a non-documentable element.
	 *
	 * @var \PHPParser_Comment_Doc
	 */
	protected $last_doc = null;

	public function __construct( $file, $validate = false, $encoding = 'utf-8' ) {
		parent::__construct( $file, false /* Force validation off */, $encoding );

		// Nullable types are unknown to the parser, pretend they don't exist.
		// The nullable type should be listed in the PHPDoc anyway.
		$this->contents = preg_replace_callback(
			'/(function [a-z0-9-_]+\([^{:]*?)\)(\:\s*\??\S+)?\s*[{;]/im',
			static function( $m ) {
				return preg_replace( '/\?(\S+)/', '$1', $m[0] ) ?: $m[0];
			},
			$this->contents
		);

		// Inline callables... ( $var )( $param ) can usualy be rewritten as $var( $param ).
		$this->contents = preg_replace_callback(
			'/(?:\s)(\(\s*\$[^()]+\))(\(.+\))/',
			static function( $m ) {
				return trim( $m[1], '() ' ) . trim( $m[2] );
			},
			$this->contents
		);

		// Short list() syntax...
		$this->contents = preg_replace_callback(
			'/(\s)\[(\s*\$[^();.*]{3,}?)\]\s*=/ism',
			static function( $m ) {
				return $m[1] . 'list( ' . $m[2] . ' ) =';
			},
			$this->contents
		);

		// Visibility on constants
		$this->contents = preg_replace_callback(
			'/\b(public|protected|private)\s+const\s+/im',
			static function( $m ) {
				return 'const ';
			},
			$this->contents
		);

		// constant arrays weren't supported.
		$this->contents = preg_replace_callback(
			'/\b(?<!\$|\[)([A-Z_]{5,})\[ ([^]]+)\]/',
			function( $m ) {
				return '$' . $m[1] . '[' . $m[2] . ']';
			},
			$this->contents
		);

		// Static calls on static calls. Mostly in tests.
		$this->contents = preg_replace_callback(
			'/([\$\[\]_a-z]+)::([\$\[\]_a-z]+)::([\$\[\]_a-z]+)/',
			static function( $m ) {
				//var_dump( $m );
				if ( 'self' === $m[1] || 'static' === $m[1] ) {
					return '$this->' . $m[2] . '->' . $m[3];
				}

				return $m[1] . '->' . $m[2]. '->' . $m[3];
			},
			$this->contents
		);

		// Static calls on function results. Mostly in tests.
		$this->contents = preg_replace_callback(
			'/([a-z_])\(\)::/',
			static function( $m ) {
				return $m[1] . '()->';
			},
			$this->contents
		);

		// Recalculate the hash, since we probably changed the contents.
		$this->hash = md5($this->contents);
	}

	/**
	 * Add hooks to the queue and update the node stack when we enter a node.
	 *
	 * If we are entering a class, function or method, we push it to the location
	 * stack. This is just so that we know whether we are in the file scope or not,
	 * so that hooks in the main file scope can be added to the file.
	 *
	 * We also check function calls to see if there are any actions or hooks. If
	 * there are, they are added to the file's hooks if in the global scope, or if
	 * we are in a function/method, they are added to the queue. They will be
	 * assigned to the function by leaveNode(). We also check for any other function
	 * calls and treat them similarly, so that we can export a list of functions
	 * used by each element.
	 *
	 * Finally, we pick up any docblocks for nodes that usually aren't documentable,
	 * so they can be assigned to the hooks to which they may belong.
	 *
	 * @param \PHPParser_Node $node
	 */
	public function enterNode( \PHPParser_Node $node ) {
		parent::enterNode( $node );

		switch ( $node->getType() ) {
			// Add classes, functions, and methods to the current location stack
			case 'Stmt_Class':
			case 'Stmt_Function':
			case 'Stmt_ClassMethod':
				array_push( $this->location, $node );
				break;

			// Parse out hook definitions and function calls and add them to the queue.
			case 'Expr_FuncCall':
				$function = new Function_Call_Reflector( $node, $this->context );

				// Add the call to the list of functions used in this scope.
				$this->getLocation()->uses['functions'][] = $function;

				if ( $this->isFilter( $node ) ) {
					if ( $this->last_doc && ! $node->getDocComment() ) {
						$node->setAttribute( 'comments', array( $this->last_doc ) );
						$this->last_doc = null;
					}

					$hook = new Hook_Reflector( $node, $this->context );

					// Add it to the list of hooks used in this scope.
					$this->getLocation()->uses['hooks'][] = $hook;
				}
				break;

			// Parse out method calls, so we can export where methods are used.
			case 'Expr_MethodCall':
				$method = new Method_Call_Reflector( $node, $this->context );

				// Add it to the list of methods used in this scope.
				$this->getLocation()->uses['methods'][] = $method;
				break;

			// Parse out method calls, so we can export where methods are used.
			case 'Expr_StaticCall':
				$method = new Static_Method_Call_Reflector( $node, $this->context );

				// Add it to the list of methods used in this scope.
				$this->getLocation()->uses['methods'][] = $method;
				break;

			// Parse out `new Class()` calls as uses of Class::__construct().
			case 'Expr_New':
				$method = new \WP_Parser\Method_Call_Reflector( $node, $this->context );

				// Add it to the list of methods used in this scope.
				$this->getLocation()->uses['methods'][] = $method;
				break;
		}

		// Pick up DocBlock from non-documentable elements so that it can be assigned
		// to the next hook if necessary. We don't do this for name nodes, since even
		// though they aren't documentable, they still carry the docblock from their
		// corresponding class/constant/function/etc. that they are the name of. If
		// we don't ignore them, we'll end up picking up docblocks that are already
		// associated with a named element, and so aren't really from a non-
		// documentable element after all.
		if ( ! $this->isNodeDocumentable( $node ) && 'Name' !== $node->getType() && ( $docblock = $node->getDocComment() ) ) {
			$this->last_doc = $docblock;
		}
	}

	/**
	 * Assign queued hooks to functions and update the node stack on leaving a node.
	 *
	 * We can now access the function/method reflectors, so we can assign any queued
	 * hooks to them. The reflector for a node isn't created until the node is left.
	 *
	 * @param \PHPParser_Node $node
	 */
	public function leaveNode( \PHPParser_Node $node ) {

		parent::leaveNode( $node );

		switch ( $node->getType() ) {
			case 'Stmt_Class':
				$class = end( $this->classes );
				if ( ! empty( $this->method_uses_queue ) ) {
					/** @var Reflection\ClassReflector\MethodReflector $method */
					foreach ( $class->getMethods() as $method ) {
						if ( isset( $this->method_uses_queue[ $method->getName() ] ) ) {
							if ( isset( $this->method_uses_queue[ $method->getName() ]['methods'] ) ) {
								/*
								 * For methods used in a class, set the class on the method call.
								 * That allows us to later get the correct class name for $this, self, parent.
								 */
								foreach ( $this->method_uses_queue[ $method->getName() ]['methods'] as $method_call ) {
									/** @var Method_Call_Reflector $method_call */
									$method_call->set_class( $class );
								}
							}

							$method->uses = $this->method_uses_queue[ $method->getName() ];
						}
					}
				}

				$this->method_uses_queue = array();
				array_pop( $this->location );
				break;

			case 'Stmt_Function':
				$function = array_pop( $this->location );
				if ( isset( $function->uses ) && ! empty( $function->uses ) ) {
					end( $this->functions )->uses = $function->uses;
				}
				break;

			case 'Stmt_ClassMethod':
				$method = array_pop( $this->location );

				/*
				 * Store the list of elements used by this method in the queue. We'll
				 * assign them to the method upon leaving the class (see above).
				 */
				if ( ! empty( $method->uses ) ) {
					$this->method_uses_queue[ $method->name ] = $method->uses;
				}
				break;
		}
	}

	/**
	 * @param \PHPParser_Node $node
	 *
	 * @return bool
	 */
	protected function isFilter( \PHPParser_Node $node ) {
		// Ignore variable functions
		if ( 'Name' !== $node->name->getType() ) {
			return false;
		}

		$calling = (string) $node->name;

		$functions = array(
			'apply_filters',
			'apply_filters_ref_array',
			'apply_filters_deprecated',
			'do_action',
			'do_action_ref_array',
			'do_action_deprecated',
		);

		return in_array( $calling, $functions );
	}

	/**
	 * @return File_Reflector
	 */
	protected function getLocation() {
		return empty( $this->location ) ? $this : end( $this->location );
	}

	/**
	 * @param \PHPParser_Node $node
	 *
	 * @return bool
	 */
	protected function isNodeDocumentable( \PHPParser_Node $node ) {
		return parent::isNodeDocumentable( $node )
		|| ( $node instanceof \PHPParser_Node_Expr_FuncCall
			&& $this->isFilter( $node ) );
	}
}
