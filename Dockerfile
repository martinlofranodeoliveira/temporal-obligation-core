FROM php:8.4-cli-bookworm@sha256:54b467ede1db96c66675e7b78457dd43729d0e7be31978d4a1e5c069ceabcf76

WORKDIR /workspace

COPY . /workspace

USER 1000:1000

CMD ["php", "bin/temporal-obligation.php", "demo", "--assert"]
