import { registerBlockType } from "@wordpress/blocks";
import edit from "./edit";
import save from "./save";

registerBlockType("lm/lexikon", {
  apiVersion: 3,
  title: "Lexikon",
  icon: "book",
  category: "widgets",
  attributes: {
    show_search: { type: "boolean", default: true },
    show_tabs: { type: "boolean", default: true },
  },
  edit,
  save,
});
