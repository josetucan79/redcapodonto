<?php
namespace Vanderbilt\REDCap\Classes\OpenAI;
class Prompts
{
    /* Check Grammar */
    const PROMPT_CHECK_GRAMMAR = "Analyze the text after --- for grammar or spelling issues. If there is an issue, answer 'Yes'. Otherwise, answer 'No'.";
    /* Fix Grammar */
    const PROMPT_FIX_GRAMMAR = "Correct any grammatical or spelling mistakes in the text.\n[STRING]";
    /* Get Reading Level (grade) */
    const PROMPT_GET_READING_LEVEL = "Generate the approximate Flesch-Kincaid grade-level (0-18) for the text after ---.";
    /* Set Reading Level */
    const PROMPT_SET_READING_LEVEL_PREFIX = "Rewrite the text after --- for a [TARGET-AUDIENCE] audience.";
    /* Increase or reduce the text by X% */
    const PROMPT_REDUCE_BY_25 = "Reduce the length of the text after ---- by 25% and output the updated text.";
    const PROMPT_REDUCE_BY_50 = "Reduce the length of the text after ---- by 50% and output the updated text.";
    const PROMPT_REDUCE_BY_75 = "Reduce the length of the text after ---- by 75% and output the updated text.";
    const PROMPT_INCREASE_BY_25 = "Increase the length of the text after ---- by 25% and output the updated text.";
    const PROMPT_INCREASE_BY_50 = "Increase the length of the text after ---- by 50% and output the updated text.";
    const PROMPT_INCREASE_BY_75 = "Increase the length of the text after ---- by 75% and output the updated text.";
    const PROMPT_TO_ONE_PARAGRAPH = "Reformulate text after ---- as a single paragraph and output the updated text.";
    /* Change tone of text */
    const PROMPT_TONE_FORMAL = "Edit the text after ---- to give a formal and polite tone and only output the updated text.";
    const PROMPT_TONE_FRIENDLY = "Edit text after ---- to give a friendly and appreciative tone and only output the updated text.";
    const PROMPT_TONE_ENCOURAGE = "Edit text after ---- to give an encouraging and motivational tone and only output the updated text.";
    const PROMPT_TONE_PROFESSIONAL = "Edit text after ---- to give a professional and concise tone and only output the updated text.";
    const PROMPT_PLACEHOLDERS_TEXT = "In your response, ensure that placeholders in the original text, which are enclosed by brackets [], remain unaltered.";
    //Correct the spelling and grammar of the following text and highlight the differences in markdown bold.:
    const PROMPT_SUMMARIZE_DEFAULT = "Summarize the following information in 150 words or less.";
    const PROMPT_TRANSLATE_STRINGS = "Translate to {TO_LANGUAGE} these {STRINGS_COUNT} texts preserving their numbers, and outputting each numbered string on a new line and without adding any additional content: {COMMA_SEP_STRINGS}";
    const SYS_TEXT_ENHANCER_SPECIFIC_QUESTION = "Limit your response to what is asked. Do not add any additional content, such as introductory remarks, explanations, etc.!";
}