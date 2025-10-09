{ lib, php }:

php.buildComposerProject (finalAttrs: {
  pname = "discord-availability";

  # Note: This must have a format that composer accepts.
  #
  # See here for more details: https://getcomposer.org/doc/04-schema.md#version
  version = "0.1.0-dev";

  src = ./.;

  vendorHash = "sha256-2CONl7m+3rNy8SjAYuoLpyS8u2uar3lYrf2bCNdBurw=";

  postPatch = ''
    rm -rf ./vendor
    sed -i '1 i #!${lib.getExe php}' ./availability.php
    chmod +x ./availability.php
  '';

  postInstall = ''
    mkdir "$out/bin"
    ln -s "$out/share/php/discord-availability/availability.php" "$out/bin/discord-availability"

    # Clean up a bit.
    rm "$out"/share/php/discord-availability/*.nix
    rm "$out"/share/php/discord-availability/composer*
    rm "$out"/share/php/discord-availability/flake.lock
    rm "$out"/share/php/discord-availability/ruleset.xml
  '';
})
