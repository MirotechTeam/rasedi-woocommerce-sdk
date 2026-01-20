#!/bin/bash

# Define the plugin slug
SLUG="rasedi-woocommerce"
BUILD_DIR="build"

echo "Packaging $SLUG..."

# Clean up previous build
rm -rf $BUILD_DIR
rm -f $SLUG.zip

# Create build directory structure
mkdir -p $BUILD_DIR/$SLUG

# Copy files
echo "Copying files..."
cp rasedi-woocommerce.php $BUILD_DIR/$SLUG/
cp README.md $BUILD_DIR/$SLUG/
cp -r includes $BUILD_DIR/$SLUG/

# Create Zip
echo "Zipping..."
cd $BUILD_DIR
zip -r ../$SLUG.zip $SLUG

# Cleanup
cd ..
rm -rf $BUILD_DIR

echo "Done! Created $SLUG.zip"
