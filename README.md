# Layout Builder Immutable Sections

Provides a new Layout Builder Section storage that allows for default sections
or regions to be marked as immutable. These immutable sections/regions will
always be pulled from the default layout and cannot be modified or deleted
by an overridden layout.

The goal of this module is to allow editors to be able to override layouts,
but still allow the default layout to be modified for those changes to be
reflected in the overridden layout.

# Disclaimer

This code is from a POC and is still in a WIP state and therefore is not yet
ready for production use.

Code cleanup, additional manual and automated testing are still required.
