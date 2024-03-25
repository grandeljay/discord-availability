{
  description = "Discord bot for DOTA 2 availability developed by Jay";

  inputs = {
    flake-utils = {
      url = "github:numtide/flake-utils";
    };
    nixpkgs = {
      url = "nixpkgs/nixos-unstable-small";
    };
    composer-nix = {
      url = "github:tristanpemble/composer-nix";
      inputs.nixpkgs.follows = "nixpkgs";
      inputs.utils.follows = "flake-utils";
    };
  };

  outputs = { self, nixpkgs, composer-nix, ... }:
    let
      supportedSystems = [ "x86_64-linux" "aarch64-linux" ];
      forAllSystems = nixpkgs.lib.genAttrs supportedSystems;
      nixpkgsFor = forAllSystems (system: import nixpkgs { inherit system; });
      version = "${nixpkgs.lib.substring 0 8 self.lastModifiedDate}.${self.shortRev or "dirty"}";
    in
    {
      packages = forAllSystems (system:
        let
          pkgs = nixpkgsFor.${system};
          composerRepo = composer-nix.lib.mkComposerRepo {
            inherit system;
            composerJson = ./composer.json;
          };
        in
        {
          default = pkgs.stdenvNoCC.mkDerivation {
            pname = "discord-availability";
            inherit version;

            src = self;

            COMPOSER = "${composerRepo}/composer.json";

            buildInputs = [ pkgs.php.packages.composer ];

            patchPhase = ''
              rm -rf ./vendor
              sed -i '1 i #!${pkgs.php}/bin/php' ./availability.php
            '';

            buildPhase = ''
              composer install --no-dev --optimize-autoloader
            '';

            installPhase = ''
              mkdir -p "$out"
              cp -r . "$out/src"
              chmod +x "$out/src/availability.php"

              mkdir "$out/bin"
              ln -s "$out/src/availability.php" "$out/bin/discord-availability"

              # Clean up a bit.
              rm "$out"/src/composer*
              rm "$out"/src/flake*
              rm "$out"/src/README.md
              rm "$out"/src/ruleset.xml
            '';
          };
        }
      );
    };
}
