<?php

if ( ! file_exists( __DIR__ . '/vendor/phpdocumentor/reflection/src/phpDocumentor/Reflection/BaseReflector.php' ) ) {
	echo "Run 'composer install' first.\n";
	exit(1);
}

$contents = file_get_contents( __DIR__ . '/vendor/phpdocumentor/reflection/src/phpDocumentor/Reflection/BaseReflector.php' );

$contents = preg_replace(
	'/function getShortName().+?{/is',
	'function getShortName() {
		if ( method_exists( $this->node, "isAnonymous" ) && $this->node->isAnonymous() ) {
		 	return "AnonymousClass";
		}
	',
	$contents
);

file_put_contents( __DIR__ . '/vendor/phpdocumentor/reflection/src/phpDocumentor/Reflection/BaseReflector.php', $contents );